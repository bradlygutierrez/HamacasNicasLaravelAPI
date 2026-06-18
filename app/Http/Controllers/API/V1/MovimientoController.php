<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Resources\V1\MovimientoResource;
use App\Http\Resources\V1\MovimientoCollection;
use App\Http\Controllers\Controller;
use App\Models\Movimiento;
use Illuminate\Validation\Rule;
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
        $validated = $request->validate([
            'inventario_hamaca_id' => ['required', 'integer', 'exists:inventario_hamacas,id'],
            'usuario_id' => ['required', 'integer', 'exists:usuarios,id'],
            'factura_id' => ['nullable', 'integer', 'exists:facturas,id'],
            'ubicacion_origen_id' => ['nullable', 'integer', 'exists:ubicaciones,id'],
            'ubicacion_destino_id' => ['nullable', 'integer', 'exists:ubicaciones,id'],
            'tipo' => ['required', Rule::in(['entrada', 'salida', 'transferencia'])],
            'cantidad' => ['required', 'integer', 'min:1'],
            'fecha' => ['sometimes', 'date'],
        ]);

        $movimiento = Movimiento::create($validated);

        return response()->json([
            'message' => 'Movimiento creado correctamente',
            'data' => new MovimientoResource($movimiento)
        ], 201);
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
            'inventario_hamaca_id' => ['sometimes', 'required', 'integer', 'exists:inventario_hamacas,id'],
            'usuario_id' => ['sometimes', 'required', 'integer', 'exists:usuarios,id'],
            'factura_id' => ['nullable', 'integer', 'exists:facturas,id'],
            'ubicacion_origen_id' => ['nullable', 'integer', 'exists:ubicaciones,id'],
            'ubicacion_destino_id' => ['nullable', 'integer', 'exists:ubicaciones,id'],
            'tipo' => ['sometimes', 'required', Rule::in(['entrada', 'salida', 'transferencia'])],
            'cantidad' => ['sometimes', 'required', 'integer', 'min:1'],
            'fecha' => ['sometimes', 'required', 'date'],
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
