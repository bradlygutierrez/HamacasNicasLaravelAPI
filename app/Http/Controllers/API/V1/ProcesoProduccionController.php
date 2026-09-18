<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcesoProduccionRequest;
use App\Http\Requests\UpdateProcesoProduccionRequest;
use App\Http\Resources\V1\ProcesoProduccionCollection;
use App\Http\Resources\V1\ProcesoProduccionResource;
use App\Models\ProcesoProduccion;
use Illuminate\Http\Request;

class ProcesoProduccionController extends Controller
{
    public function index(Request $request): ProcesoProduccionCollection
    {
        $procesos = ProcesoProduccion::query()
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

        return new ProcesoProduccionCollection($procesos);
    }

    public function store(StoreProcesoProduccionRequest $request): ProcesoProduccionResource
    {
        return new ProcesoProduccionResource(
            ProcesoProduccion::create([
                ...$request->validated(),
                'state' => true,
            ])
        );
    }

    public function show(ProcesoProduccion $procesoProduccion): ProcesoProduccionResource
    {
        return new ProcesoProduccionResource($procesoProduccion);
    }

    public function update(
        UpdateProcesoProduccionRequest $request,
        ProcesoProduccion $procesoProduccion
    ): ProcesoProduccionResource {
        $procesoProduccion->update($request->validated());

        return new ProcesoProduccionResource($procesoProduccion->fresh());
    }

    public function destroy(ProcesoProduccion $procesoProduccion): ProcesoProduccionResource
    {
        $procesoProduccion->update(['state' => false]);

        return new ProcesoProduccionResource($procesoProduccion->fresh());
    }
}
