<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateServicioFormulaRequest;
use App\Models\ServicioAdicional;
use App\Services\CostoProduccionService;
use App\Services\ServicioFormulaService;

class ServicioFormulaController extends Controller
{
    public function __construct(
        private readonly ServicioFormulaService $formulaService,
        private readonly CostoProduccionService $costoService
    ) {
    }

    public function show(ServicioAdicional $servicioAdicional)
    {
        return response()->json(['data' => $this->payload($servicioAdicional)]);
    }

    public function update(UpdateServicioFormulaRequest $request, ServicioAdicional $servicioAdicional)
    {
        return response()->json(['data' => $this->payload(
            $this->formulaService->update($servicioAdicional, $request->validated())
        )]);
    }

    public function costs(ServicioAdicional $servicioAdicional)
    {
        return response()->json(['data' => $this->costoService->calcularServicio($servicioAdicional)]);
    }

    private function payload(ServicioAdicional $service): array
    {
        $service->load(['materiales.material', 'manoObra.proceso']);

        return [
            'id' => $service->id,
            'nombre' => $service->nombre,
            'alcance' => $service->alcance,
            'metodo_calculo' => $service->metodo_calculo,
            'precio_venta_actual' => $service->precio_venta_actual,
            'costo_actual' => $service->costo_actual,
            'materiales' => $service->materiales,
            'mano_obra' => $service->manoObra,
        ];
    }
}
