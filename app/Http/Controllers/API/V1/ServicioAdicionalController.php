<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServicioAdicionalRequest;
use App\Http\Requests\UpdateServicioAdicionalRequest;
use App\Http\Resources\V1\ServicioAdicionalCollection;
use App\Http\Resources\V1\ServicioAdicionalResource;
use App\Models\ServicioAdicional;
use App\Services\ServicioAdicionalService;
use Illuminate\Http\Request;

class ServicioAdicionalController extends Controller
{
    public function __construct(private readonly ServicioAdicionalService $servicioService)
    {
    }

    public function index(Request $request): ServicioAdicionalCollection
    {
        $servicios = ServicioAdicional::query()
            ->when(
                !$request->boolean('incluir_inactivos'),
                fn ($query) => $query->where('state', true)
            )
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(function ($query) use ($request): void {
                    $term = '%' . $request->string('search') . '%';
                    $query->where('nombre', 'like', $term)
                        ->orWhere('codigo', 'like', $term);
                })
            )
            ->orderBy('nombre')
            ->paginate(50);

        return new ServicioAdicionalCollection($servicios);
    }

    public function store(StoreServicioAdicionalRequest $request): ServicioAdicionalResource
    {
        $servicio = $this->servicioService->create(
            $request->validated(),
            $request->user()
        );

        return new ServicioAdicionalResource($servicio);
    }

    public function show(ServicioAdicional $servicioAdicional): ServicioAdicionalResource
    {
        return new ServicioAdicionalResource($servicioAdicional);
    }

    public function update(
        UpdateServicioAdicionalRequest $request,
        ServicioAdicional $servicioAdicional
    ): ServicioAdicionalResource {
        $servicio = $this->servicioService->update(
            $servicioAdicional,
            $request->validated(),
            $request->user()
        );

        return new ServicioAdicionalResource($servicio);
    }

    public function destroy(ServicioAdicional $servicioAdicional): ServicioAdicionalResource
    {
        return new ServicioAdicionalResource($this->servicioService->deactivate($servicioAdicional));
    }
}
