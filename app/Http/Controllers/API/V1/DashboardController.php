<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;


class DashboardController extends Controller
{
    public function summary()
    {
        $now = now();

        $existenciaActual = (int) DB::table('inventario_hamacas')->sum('cantidad');

        $entradasMes = (int) DB::table('movimientos')
            ->where('tipo', 'entrada')
            ->whereYear('fecha', $now->year)
            ->whereMonth('fecha', $now->month)
            ->sum('cantidad');

        $salidasMes = (int) DB::table('movimientos')
            ->where('tipo', 'salida')
            ->whereYear('fecha', $now->year)
            ->whereMonth('fecha', $now->month)
            ->sum('cantidad');

        $stockStats = DB::table('inventario_hamacas')
            ->selectRaw('MIN(cantidad) as stock_minimo, MAX(cantidad) as stock_maximo, SUM(cantidad) as unidades_totales')
            ->first();

        return response()->json([
            'data' => [
                'existencia_actual_total' => $existenciaActual,
                'entradas_mes' => $entradasMes,
                'salidas_mes' => $salidasMes,
                'stock_minimo' => (int) ($stockStats->stock_minimo ?? 0),
                'stock_maximo' => (int) ($stockStats->stock_maximo ?? 0),
                'unidades_totales' => (int) ($stockStats->unidades_totales ?? 0),
                'stock_por_categoria' => $this->stockByCategory(),
                'entradas_salidas_por_categoria' => $this->movementsByCategoryData(),
            ],
        ]);
    }

    public function movementsByCategory()
    {
        return response()->json([
            'data' => $this->movementsByCategoryData(),
        ]);
    }

    public function categoryStats($categoriaId)
    {
        $data = DB::table('inventario_hamacas')
            ->join('hamacas', 'inventario_hamacas.hamaca_id', '=', 'hamacas.id')
            ->where('hamacas.categoria_id', $categoriaId)
            ->select(
                DB::raw('MIN(inventario_hamacas.cantidad) as stock_minimo'),
                DB::raw('MAX(inventario_hamacas.cantidad) as stock_maximo'),
                DB::raw('SUM(inventario_hamacas.cantidad) as unidades_totales'),
                DB::raw('COUNT(DISTINCT inventario_hamacas.id) as total_productos')
            )
            ->first();

        return response()->json($data);
    }

    private function stockByCategory()
    {
        return DB::table('inventario_hamacas')
            ->join('hamacas', 'inventario_hamacas.hamaca_id', '=', 'hamacas.id')
            ->join('categorias', 'hamacas.categoria_id', '=', 'categorias.id')
            ->select(
                'categorias.id as categoria_id',
                'categorias.nombre as categoria',
                DB::raw('SUM(inventario_hamacas.cantidad) as stock')
            )
            ->groupBy('categorias.id', 'categorias.nombre')
            ->orderBy('categorias.nombre')
            ->get();
    }

    private function movementsByCategoryData()
    {
        return DB::table('movimientos')
            ->join('inventario_hamacas', 'movimientos.inventario_hamaca_id', '=', 'inventario_hamacas.id')
            ->join('hamacas', 'inventario_hamacas.hamaca_id', '=', 'hamacas.id')
            ->join('categorias', 'hamacas.categoria_id', '=', 'categorias.id')
            ->select(
                'categorias.id as categoria_id',
                'categorias.nombre as categoria',
                DB::raw("SUM(CASE WHEN movimientos.tipo='entrada' THEN movimientos.cantidad ELSE 0 END) as entradas"),
                DB::raw("SUM(CASE WHEN movimientos.tipo='salida' THEN movimientos.cantidad ELSE 0 END) as salidas"),
                DB::raw("SUM(CASE WHEN movimientos.tipo='transferencia' THEN movimientos.cantidad ELSE 0 END) as transferencias")
            )
            ->groupBy('categorias.id', 'categorias.nombre')
            ->orderBy('categorias.nombre')
            ->get();
    }
}
