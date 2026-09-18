<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Hamaca;
use App\Services\CostoProduccionService;
use Illuminate\Http\Request;

class FormulaController extends Controller
{
    public function __construct(private readonly CostoProduccionService $costoService)
    {
    }

    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $hamacas = Hamaca::query()
            ->with([
                'categoria',
                'tamano',
                'recetas' => fn ($query) => $query
                    ->whereIn('estado', ['activa', 'borrador'])
                    ->with(['detallesMateriales.material', 'detallesManoObra.proceso']),
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->where('nombre', 'like', '%' . $request->string('search') . '%');
            })
            ->latest()
            ->paginate($perPage);

        $data = $hamacas->getCollection()->map(function (Hamaca $hamaca): array {
            $active = $hamaca->recetas->firstWhere('estado', 'activa');
            $draft = $hamaca->recetas->firstWhere('estado', 'borrador');

            return [
                'id' => $hamaca->id,
                'nombre' => $hamaca->nombre,
                'categoria' => $hamaca->categoria?->nombre,
                'tamano' => $hamaca->tamano?->nombre,
                'precio' => $hamaca->precio,
                'receta_activa' => $active ? [
                    'id' => $active->id,
                    'version' => (int) $active->version,
                ] : null,
                'receta_borrador' => $draft ? [
                    'id' => $draft->id,
                    'version' => (int) $draft->version,
                ] : null,
                'costo_produccion' => $active
                    ? $this->costoService->calcularReceta($active)['resumen']['costo_produccion']
                    : null,
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $hamacas->currentPage(),
                'last_page' => $hamacas->lastPage(),
                'per_page' => $hamacas->perPage(),
                'total' => $hamacas->total(),
            ],
        ]);
    }
}
