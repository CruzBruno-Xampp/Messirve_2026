<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Stock;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Calcula el importe de una venta sin registrar ventas ni modificar stock.
 * Usa las reglas de descuentos y recargo de VentaController::guardar.
 * Es una cotización: al confirmar deberá revalidarse el stock y el total.
 */
class CalculadoraVentaLocal
{
    public function calcular(array $datos): array
    {
        $idSucursal = (int) $datos['idsucursal'];
        $sucursal = Sucursal::query()
            ->where('idsucursal', $idSucursal)
            ->where('estado', 1)
            ->first();

        if (!$sucursal) {
            throw ValidationException::withMessages([
                'idsucursal' => 'La sucursal ya no se encuentra activa.',
            ]);
        }

        $metodoPago = MetodoPago::query()
            ->where('idmetodopago', (int) $datos['idmetodopago'])
            ->where('estado', 1)
            ->first();

        if (!$metodoPago) {
            throw ValidationException::withMessages([
                'idmetodopago' => 'El medio de pago ya no está disponible.',
            ]);
        }

        $porcentajeRecargo = round((float) $metodoPago->recargo, 2);
        if ($porcentajeRecargo < 0 || $porcentajeRecargo > 100) {
            throw ValidationException::withMessages([
                'idmetodopago' => 'El recargo configurado no es válido.',
            ]);
        }

        $idCliente = null;
        $descuentoCliente = 0.0;

        if ($datos['tipo_cliente'] === 'registrado') {
            $cliente = Cliente::query()
                ->with('tipoCliente')
                ->where('idcliente', (int) ($datos['idcliente'] ?? 0))
                ->where('estado', 1)
                ->first();

            if (!$cliente) {
                throw ValidationException::withMessages([
                    'idcliente' => 'El cliente no existe o está inactivo.',
                ]);
            }

            $idCliente = (int) $cliente->idcliente;
            $tipo = $cliente->tipoCliente;
            if ($tipo && (int) $tipo->estado === 1 && (bool) $tipo->aplica_local) {
                $descuentoCliente = max(0.0, min(100.0, (float) $tipo->descuento));
            }
        } elseif ($datos['tipo_cliente'] !== 'consumidor_final') {
            throw ValidationException::withMessages([
                'tipo_cliente' => 'El tipo de cliente no es válido.',
            ]);
        }

        $descuentoExtra = round((float) ($datos['descuento_adicional'] ?? 0), 2);
        if ($descuentoExtra < 0 || $descuentoExtra > 100) {
            throw ValidationException::withMessages([
                'descuento_adicional' => 'El descuento no es válido.',
            ]);
        }

        $lineas = collect($datos['productos'] ?? []);
        if ($lineas->isEmpty() || $lineas->count() > 100) {
            throw ValidationException::withMessages([
                'productos' => 'Ingresá entre 1 y 100 productos.',
            ]);
        }

        $ids = $lineas->pluck('idproducto')->map(fn ($id) => (int) $id);
        if ($ids->unique()->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'productos' => 'Hay productos repetidos.',
            ]);
        }

        $productos = DB::table('productos')
            ->whereIn('idproducto', $ids)
            ->where('estado', 1)
            ->get(['idproducto', 'descripcion', 'pvp', 'idiva'])
            ->keyBy('idproducto');

        $disponible = Stock::query()
            ->join('sucursales', 'sucursales.idsucursal', '=', 'stock.idsucursal')
            ->whereIn('stock.idproducto', $ids)
            ->where('stock.estado', 1)
            ->where('sucursales.estado', 1)
            ->groupBy('stock.idproducto')
            ->select('stock.idproducto', DB::raw('SUM(stock.stockactual) AS disponible'))
            ->pluck('disponible', 'idproducto');

        $importe = 0.0;
        foreach ($lineas as $indice => $linea) {
            $id = (int) ($linea['idproducto'] ?? 0);
            $cantidad = (int) ($linea['cantidad'] ?? 0);
            $producto = $productos->get($id);
            if ($cantidad < 1 || !$producto || (float) $producto->pvp <= 0) {
                throw ValidationException::withMessages([
                    "productos.{$indice}" => 'Hay un producto o cantidad inválidos.',
                ]);
            }
            if ((int) ($disponible->get($id) ?? 0) < $cantidad) {
                throw ValidationException::withMessages([
                    "productos.{$indice}" => 'Stock insuficiente para ' . $producto->descripcion . '.',
                ]);
            }

            // Misma secuencia y redondeo que VentaController::guardar.
            $subtotal = round(round((float) $producto->pvp, 2) * $cantidad, 2);
            $importeDescuentoCliente = round($subtotal * ($descuentoCliente / 100), 2);
            $restante = round($subtotal - $importeDescuentoCliente, 2);
            $importeDescuentoExtra = round($restante * ($descuentoExtra / 100), 2);
            $importe = round(
                $importe + round($subtotal - round($importeDescuentoCliente + $importeDescuentoExtra, 2), 2),
                2
            );
        }

        $recargo = round($importe * ($porcentajeRecargo / 100), 2);
        $total = round($importe + $recargo, 2);
        if ($total <= 0) {
            throw ValidationException::withMessages([
                'productos' => 'El total a cobrar debe ser mayor a cero.',
            ]);
        }

        return [
            'idcliente' => $idCliente,
            'descuento_cliente_porcentaje' => $descuentoCliente,
            'descuento_adicional_porcentaje' => $descuentoExtra,
            'recargo_porcentaje' => $porcentajeRecargo,
            'base' => $importe,
            'recargo' => $recargo,
            'total' => $total,
        ];
    }
}
