"""Puerta HTTP local para PRUEBAS de Mercado Pago QR.
Cloudflare -> localhost:8787 -> SOLO POST /webhooks/mercadopago/qr -> Laravel localhost:8000.
La autenticación del evento sigue a cargo del validador de firma Laravel.
"""
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from http.client import HTTPConnection, HTTPException
from urllib.parse import urlsplit

LISTEN = ('127.0.0.1', 8787)
UPSTREAM = ('127.0.0.1', 8000)
ALLOWED_PATH = '/webhooks/mercadopago/qr'
MAX_BODY_BYTES = 64 * 1024
MAX_QUERY_CHARS = 2048


class Handler(BaseHTTPRequestHandler):
    server_version = 'MessirveWebhookGate/1.0'
    sys_version = ''
    protocol_version = 'HTTP/1.1'

    def log_message(self, format, *args):
        # Never log headers, credentials or webhook bodies.
        print('[puerta] ' + (format % args), flush=True)

    def send_reply(self, status: int, body: bytes):
        self.send_response(status)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Cache-Control', 'no-store')
        self.send_header('X-Content-Type-Options', 'nosniff')
        self.end_headers()
        self.wfile.write(body)

    def do_POST(self):
        parsed = urlsplit(self.path)
        if parsed.path != ALLOWED_PATH or parsed.fragment or len(parsed.query) > MAX_QUERY_CHARS:
            return self.send_reply(404, b'{"message":"Not found"}')
        if self.headers.get('Transfer-Encoding'):
            return self.send_reply(400, b'{"message":"Unsupported transfer encoding"}')
        try:
            raw_len = self.headers.get('Content-Length')
            length = int(raw_len) if raw_len is not None else -1
        except ValueError:
            length = -1
        if not 0 <= length <= MAX_BODY_BYTES:
            return self.send_reply(413, b'{"message":"Invalid payload size"}')
        content_type = self.headers.get('Content-Type', '')
        if not (content_type.lower().startswith('application/json') and len(content_type) <= 150):
            return self.send_reply(415, b'{"message":"JSON required"}')
        body = self.rfile.read(length)
        # Only allowlisted headers are passed to Laravel. No cookies, auth or forwarded headers.
        headers = {
            'Host': '127.0.0.1:8000',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Content-Length': str(len(body)),
        }
        for incoming, outgoing in [('x-signature', 'x-signature'), ('x-request-id', 'x-request-id')]:
            value = self.headers.get(incoming, '')
            if len(value) > 1024:
                return self.send_reply(400, b'{"message":"Invalid header"}')
            if value:
                headers[outgoing] = value
        conn = HTTPConnection(*UPSTREAM, timeout=30)
        try:
            uri = ALLOWED_PATH + ('?' + parsed.query if parsed.query else '')
            conn.request('POST', uri, body=body, headers=headers)
            response = conn.getresponse()
            result = response.read(64 * 1024 + 1)
            if len(result) > 64 * 1024:
                return self.send_reply(502, b'{"message":"Upstream response too large"}')
            # Preserve Laravel's status; never forward session cookies or redirects.
            if response.status not in (200, 201, 202, 204, 400, 401, 403, 404, 409, 422, 429, 500, 502, 503):
                return self.send_reply(502, b'{"message":"Unexpected upstream status"}')
            return self.send_reply(response.status, result)
        except (OSError, HTTPException) as exc:
            print('[puerta] Laravel no responde: ' + type(exc).__name__, flush=True)
            return self.send_reply(502, b'{"message":"Laravel unavailable"}')
        finally:
            conn.close()

    def do_GET(self):
        self.send_reply(404, b'{"message":"Not found"}')

    def do_HEAD(self):
        self.send_reply(404, b'')

    def do_PUT(self):
        self.send_reply(404, b'{"message":"Not found"}')

    def do_PATCH(self):
        self.send_reply(404, b'{"message":"Not found"}')

    def do_DELETE(self):
        self.send_reply(404, b'{"message":"Not found"}')

    def do_OPTIONS(self):
        self.send_reply(404, b'{"message":"Not found"}')


if __name__ == '__main__':
    print('Puerta QR local en http://127.0.0.1:8787', flush=True)
    print('Solo POST /webhooks/mercadopago/qr -> Laravel 127.0.0.1:8000', flush=True)
    print('Ctrl+C para detener. NO usar como servidor de produccion.', flush=True)
    try:
        ThreadingHTTPServer(LISTEN, Handler).serve_forever()
    except KeyboardInterrupt:
        print('\nPuerta detenida.', flush=True)
