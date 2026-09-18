<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;
use App\Http\Resources\V1\MaterialCollection;
use App\Http\Resources\V1\MaterialResource;
use App\Models\Material;
use App\Services\MaterialService;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function __construct(private readonly MaterialService $materialService)
    {
    }

    public function index(Request $request): MaterialCollection
    {
        $materials = Material::query()
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

        return new MaterialCollection($materials);
    }

    public function store(StoreMaterialRequest $request): MaterialResource
    {
        $material = $this->materialService->create(
            $request->validated(),
            $request->user()
        );

        return new MaterialResource($material);
    }

    public function show(Material $material): MaterialResource
    {
        return new MaterialResource($material);
    }

    public function update(UpdateMaterialRequest $request, Material $material): MaterialResource
    {
        $material = $this->materialService->update(
            $material,
            $request->validated(),
            $request->user()
        );

        return new MaterialResource($material);
    }

    public function destroy(Material $material): MaterialResource
    {
        return new MaterialResource($this->materialService->deactivate($material));
    }
}
