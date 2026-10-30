
(async () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!token) {
        console.error('No se encontró el token CSRF.');
        return;
    }

    try {
        const respuesta = await fetch('/ventas/qr/confirmar', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify({
                referencia: 'fcc858bb-ff03-454a-b59f-a7652bab187a'
            })
        });

        console.log('Código HTTP:', respuesta.status);
        console.log('Respuesta:', await respuesta.json());
    } catch (error) {
        console.error('Error durante la prueba:', error);
    }
})();
