<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Resources\V1\MovimientoResource;
use App\Http\Resources\V1\MovimientoCollection;
use App\Http\Controllers\Controller;
use App\Models\Movimiento;
use Illuminate\Http\Request;

class MovimientoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return new MovimientoCollection(Movimiento::latest()->paginate());
    }

    /**
     * Store a newly created resource in storage.
     */
   public function store(Request $request)
{
    try {
        $validated = $request->validate([
            'usuario_id' => 'required|integer',
            'hamaca_id' => 'required|integer',
            'tipo' => 'required|string|max:50',
            'cantidad' => 'required|integer',
            'fecha' => 'required|date',
        ]);

        $movimiento = Movimiento::create($validated);

        return response()->json([
            'message' => 'Movimiento creado correctamente',
            'data' => new MovimientoResource($movimiento)
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error creando movimiento',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Display the specified resource.
     */
    public function show(Movimiento $movimiento)
    {
        return new MovimientoResource($movimiento);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Movimiento $movimiento)
    {
        $validated = $request->validate([
            'usuario_id' => 'sometimes|required|integer',
            'hamaca_id' => 'sometimes|required|integer',
            'tipo' => 'sometimes|required|string|max:50',
            'cantidad' => 'sometimes|required|integer',
            'fecha' => 'sometimes|required|date',
        ]);

        $movimiento->update($validated);

        return response()->json([
            'message' => 'Movimiento actualizado correctamente',
            'data' => new MovimientoResource($movimiento)
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Movimiento $movimiento)
    {
        $movimiento->delete();

        return response()->json([
            'message' => 'Movimiento eliminado correctamente'
        ], 200);
    }

    public function getMonthlyEntries()
    {
        $entries = Movimiento::where('tipo', 'entrada')
            ->whereYear('fecha', now()->year)
            ->whereMonth('fecha', now()->month)
            ->sum('cantidad');

        return response()->json([
            'entries' => $entries
        ]);
    }

    public function getMonthlyExits()
    {
        $exits = Movimiento::where('tipo', 'salida')
            ->whereYear('fecha', now()->year)
            ->whereMonth('fecha', now()->month)
            ->sum('cantidad');

        return response()->json([
            'exits' => $exits
        ]);
    }
}
