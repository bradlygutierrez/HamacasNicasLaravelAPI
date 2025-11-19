<?php

namespace App\Http\Controllers\API\V1;

use App\Models\DetalleFactura;
use App\Http\Resources\V1\DetalleFacturaResource;
use App\Http\Resources\V1\DetalleFacturaCollection;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DetalleFacturaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return new DetalleFacturaCollection(DetalleFactura::latest()->paginate());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'factura_id' => 'required|exists:facturas,id',
            'hamaca_id' => 'required|exists:hamacas,id',
            'cantidad' => 'required|integer|min:1',
            'precio_unitario' => 'required|numeric|min:0',
        ]);

        $detalleFactura = DetalleFactura::create($validatedData);

        return response()->json([
            'message' => 'Detalle de factura creado correctamente',
            'data' => new DetalleFacturaResource($detalleFactura)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(DetalleFactura $detalleFactura)
    {
        return new DetalleFacturaResource($detalleFactura);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DetalleFactura $detalleFactura)
    {
        $validatedData = $request->validate([
            'factura_id' => 'sometimes|exists:facturas,id',
            'hamaca_id' => 'sometimes|exists:hamacas,id',
            'cantidad' => 'sometimes|integer|min:1',
            'precio_unitario' => 'sometimes|numeric|min:0',
        ]);

        $detalleFactura->update($validatedData);

        return response()->json([
            'message' => 'Detalle de factura actualizado correctamente',
            'data' => new DetalleFacturaResource($detalleFactura)
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DetalleFactura $detalleFactura)
    {
        $detalleFactura -> delete();
        return response()->json([
            'message' => 'Detalle de factura eliminado correctamente'
        ], 200);
    }
}
