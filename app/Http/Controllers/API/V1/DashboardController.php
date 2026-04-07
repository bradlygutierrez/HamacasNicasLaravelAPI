<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class DashboardController extends Controller
{

    public function movementsByCategory()
    {
        $data = DB::table('movimientos')
            ->join('hamacas', 'movimientos.hamaca_id', '=', 'hamacas.id')
            ->join('categorias', 'hamacas.categoria_id', '=', 'categorias.id')
            ->select(
                'categorias.nombre as categoria',
                DB::raw("SUM(CASE WHEN movimientos.tipo='entrada' THEN movimientos.cantidad ELSE 0 END) as entradas"),
                DB::raw("SUM(CASE WHEN movimientos.tipo='salida' THEN movimientos.cantidad ELSE 0 END) as salidas")
            )
            ->groupBy('categorias.nombre')
            ->get();

        return response()->json([
            'data' => $data
        ]);
    }


    public function categoryStats($categoriaId)
    {
        $data = DB::table('hamacas')
            ->where('categoria_id', $categoriaId)
            ->select(
                DB::raw('MIN(cantidad) as stock_minimo'),
                DB::raw('MAX(cantidad) as stock_maximo'),
                DB::raw('COUNT(*) as total_productos')
            )
            ->first();

        return response()->json($data);
    }
}
