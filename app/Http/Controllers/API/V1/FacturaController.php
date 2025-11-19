<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Http\Resources\V1\FacturaResource;
use App\Http\Resources\V1\FacturaCollection;
use Illuminate\Http\Request;

class FacturaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return new FacturaCollection(Factura::latest()->paginate());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'usuario_id' => 'required|integer|exists:usuarios,id',
            'fecha' => 'required|date',
            'total' => 'required|numeric',
        ]);

        $factura = Factura::create($validated);

        return response()->json([
            'message' => 'Factura creada correctamente',
            'data' => new FacturaResource($factura)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Factura $factura)
    {
        return new FacturaResource($factura);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Factura $factura)
    {
        $validated = $request->validate([
            'usuario_id' => 'sometimes|integer|exists:usuarios,id',
            'fecha' => 'sometimes|date',
            'total' => 'sometimes|numeric',
        ]);

        $factura->update($validated);

        return response()->json([
            'message' => 'Factura actualizada correctamente',
            'data' => new FacturaResource($factura)
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Factura $factura)
    {
        $factura -> delete();
        return response()->json([
            'message' => 'Factura eliminada correctamente'
        ], 200);
    }
}
