<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVentaRequest;
use App\Http\Resources\V1\FacturaResource;
use App\Services\VentaService;

class PosVentaController extends Controller
{
    public function __construct(private readonly VentaService $ventaService)
    {
    }

    public function store(StoreVentaRequest $request)
    {
        $factura = $this->ventaService->crearVenta([
            ...$request->validated(),
            'vendedor_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Venta registrada correctamente.',
            'data' => new FacturaResource($factura),
        ], 201);
    }
}
