<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\HamacaVariante;
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
        $variantes = HamacaVariante::query()
            ->where('state', true)
            ->with([
                'hamaca.categoria',
                'hamaca.tamano',
                'colores',
                'recetas' => fn ($query) => $query
                    ->whereIn('estado', ['activa', 'borrador'])
                    ->with(['detallesMateriales.material', 'detallesManoObra.proceso']),
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->whereHas('hamaca', fn ($hamaca) => $hamaca
                    ->where('nombre', 'like', '%' . $request->string('search') . '%'));
            })
            ->when($request->filled('hamaca_id'), fn ($query) => $query->where('hamaca_id', $request->integer('hamaca_id')))
            ->latest()
            ->paginate($perPage);

        $data = $variantes->getCollection()->map(function (HamacaVariante $variante): array {
            $active = $variante->recetas->firstWhere('estado', 'activa');
            $draft = $variante->recetas->firstWhere('estado', 'borrador');

            return [
                'id' => $variante->id,
                'hamaca_id' => $variante->hamaca_id,
                'nombre' => $variante->hamaca?->nombre,
                'hamaca' => [
                    'id' => $variante->hamaca?->id,
                    'nombre' => $variante->hamaca?->nombre,
                    'categoria' => $variante->hamaca?->categoria?->nombre,
                    'tamano' => $variante->hamaca?->tamano?->nombre,
                    'precio' => $variante->hamaca?->precio,
                ],
                'variante' => [
                    'id' => $variante->id,
                    'nombre' => $variante->nombre,
                    'colores' => $variante->colores->map(fn ($color) => ['id' => $color->id, 'nombre' => $color->nombre])->values(),
                ],
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
                'current_page' => $variantes->currentPage(),
                'last_page' => $variantes->lastPage(),
                'per_page' => $variantes->perPage(),
                'total' => $variantes->total(),
            ],
        ]);
    }
}
