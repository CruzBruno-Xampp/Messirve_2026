<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Stock;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const ZONA_HORARIA = 'America/Argentina/Buenos_Aires';

    /**
     * Panel de inicio del sistema administrativo.
     */
    public function index(): View
    {
        $usuario = auth('web')->user();
        $usuario->loadMissing('rol', 'sucursal');

        $esDuenoGlobal = strtoupper((string) $usuario->rol?->nombre) === 'DUEÑO'
            && $usuario->idsucursal === null;

        abort_if(
            !$esDuenoGlobal && $usuario->idsucursal === null,
            403,
            'Tu cuenta no tiene una sucursal asignada para consultar el dashboard.'
        );

        $idSucursal = $esDuenoGlobal
            ? null
            : (int) $usuario->idsucursal;

        $puedeVerVentas = $usuario->tienePermiso('ventas.ver');
        $puedeVerCompras = $usuario->tienePermiso('compras.ver');
        $puedeVerStock = $usuario->tienePermiso('stock.ver');

        $hoy = now(self::ZONA_HORARIA);
        $fechaHoy = $hoy->toDateString();

        $ventasBase = Venta::query()
            ->when(
                $idSucursal,
                fn (Builder $consulta) => $consulta->where('idsucursal', $idSucursal)
            );

        $ventasValidas = (clone $ventasBase)
            ->whereNotIn('estado', ['ANULADA', 'CANCELADA']);

        $ventasHoy = (clone $ventasValidas)
            ->whereDate('fecha', $fechaHoy);

        $comprasBase = Compra::query()
            ->when(
                $idSucursal,
                fn (Builder $consulta) => $consulta->where('idsucursal', $idSucursal)
            );

        $stockBase = Stock::query()
            ->where('stock.estado', 1)
            ->whereHas(
                'producto',
                fn (Builder $consulta) => $consulta->where('estado', 1)
            )
            ->when(
                $idSucursal,
                fn (Builder $consulta) => $consulta->where('stock.idsucursal', $idSucursal)
            );

        $resumen = [
            'ventasHoy' => $puedeVerVentas ? (clone $ventasHoy)->count() : null,
            'totalHoy' => $puedeVerVentas ? (float) (clone $ventasHoy)->sum('total') : null,
            'comprasPendientes' => $puedeVerCompras
                ? (clone $comprasBase)->whereIn('estado', ['PENDIENTE', 'PARCIAL'])->count()
                : null,
            'stockBajo' => $puedeVerStock
                ? (clone $stockBase)
                    ->whereColumn('stock.stockactual', '<=', 'stock.stockminimo')
                    ->count()
                : null,
        ];

        $ultimasVentas = $puedeVerVentas
            ? (clone $ventasBase)
                ->with(['cliente', 'usuario', 'sucursal'])
                ->orderByDesc('fecha')
                ->orderByDesc('idventa')
                ->limit(6)
                ->get()
            : collect();

        $alertasStock = $puedeVerStock
            ? (clone $stockBase)
                ->with(['producto', 'sucursal'])
                ->whereColumn('stock.stockactual', '<=', 'stock.stockminimo')
                ->orderBy('stock.stockactual')
                ->orderBy('stock.stockminimo')
                ->limit(7)
                ->get()
            : collect();

        $comprasPendientes = $puedeVerCompras
            ? (clone $comprasBase)
                ->with(['proveedor', 'sucursal'])
                ->whereIn('estado', ['PENDIENTE', 'PARCIAL'])
                ->orderByDesc('fecha')
                ->orderByDesc('idcompra')
                ->limit(6)
                ->get()
            : collect();

        $inicioGrafico = $hoy->copy()->subDays(6)->startOfDay();

        $ventasGrafico = $puedeVerVentas
            ? (clone $ventasValidas)
                ->whereDate('fecha', '>=', $inicioGrafico->toDateString())
                ->whereDate('fecha', '<=', $fechaHoy)
                ->orderBy('fecha')
                ->get(['fecha', 'total'])
            : collect();

        $totalesPorFecha = $ventasGrafico
            ->groupBy(fn (Venta $venta) => $venta->fecha->timezone(self::ZONA_HORARIA)->format('Y-m-d'))
            ->map(fn ($ventas) => (float) $ventas->sum('total'));

        $ventasPorDia = collect(range(0, 6))
            ->map(function (int $desplazamiento) use ($inicioGrafico, $totalesPorFecha) {
                $fecha = $inicioGrafico->copy()->addDays($desplazamiento);
                $clave = $fecha->format('Y-m-d');

                return [
                    'fecha' => $clave,
                    'etiqueta' => $fecha->format('d/m'),
                    'total' => (float) ($totalesPorFecha[$clave] ?? 0),
                ];
            });

        $maximoGrafico = max(1, (float) $ventasPorDia->max('total'));

        $ventasPorDia = $ventasPorDia->map(function (array $dia) use ($maximoGrafico) {
            $dia['porcentaje'] = $dia['total'] > 0
                ? max(7, round(($dia['total'] / $maximoGrafico) * 100, 2))
                : 4;

            return $dia;
        });

        return view('dashboard.index', [
            'usuario' => $usuario,
            'esDuenoGlobal' => $esDuenoGlobal,
            'sucursalActual' => $usuario->sucursal,
            'resumen' => $resumen,
            'ultimasVentas' => $ultimasVentas,
            'alertasStock' => $alertasStock,
            'comprasPendientes' => $comprasPendientes,
            'ventasPorDia' => $ventasPorDia,
            'fechaActual' => $hoy,
            'puedeVerVentas' => $puedeVerVentas,
            'puedeVerCompras' => $puedeVerCompras,
            'puedeVerStock' => $puedeVerStock,
        ]);
    }
}
