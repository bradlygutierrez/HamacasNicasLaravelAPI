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
    public function index(\Illuminate\Http\Request $request)
    {
        if ($request->user()->rol === 'almacenista') abort(403, 'No tenés permiso para consultar facturas.');
        return new FacturaCollection(
            Factura::with(['cliente', 'usuario', 'pedido'])->when($request->user()->rol === 'vendedor', fn ($q) => $q->where('vendedor_id', $request->user()->id))->latest()->paginate()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function show(\Illuminate\Http\Request $request, Factura $factura)
    {
        if ($request->user()->rol === 'almacenista' || ($request->user()->rol === 'vendedor' && $factura->vendedor_id !== $request->user()->id)) abort(403, 'No tenés acceso a esta factura.');
        return new FacturaResource($factura->load(['cliente', 'usuario', 'pedido', 'detalles.servicios', 'servicios']));
    }
}
