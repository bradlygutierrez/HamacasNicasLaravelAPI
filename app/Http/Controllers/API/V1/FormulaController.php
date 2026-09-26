<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Hamaca;
use App\Services\CostoProduccionService;
use Illuminate\Http\Request;

class FormulaController extends Controller
{
    public function __construct(private readonly CostoProduccionService $costoService) {}

    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $term = '%' . $request->string('search') . '%';
        $hamacas = Hamaca::with(['categoria', 'tamano', 'colores', 'recetas' => fn ($q) => $q->whereIn('estado', ['activa', 'borrador'])->with(['detallesMateriales.material', 'detallesManoObra.proceso'])])
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($term) {
                $q->where('nombre', 'like', $term)->orWhereHas('categoria', fn ($x) => $x->where('nombre', 'like', $term))
                    ->orWhereHas('tamano', fn ($x) => $x->where('nombre', 'like', $term))->orWhereHas('colores', fn ($x) => $x->where('nombre', 'like', $term));
            }))->when($request->filled('hamaca_id'), fn ($q) => $q->whereKey($request->integer('hamaca_id')))->latest()->paginate($perPage);
        $data = $hamacas->getCollection()->map(function (Hamaca $hamaca) {
            $active = $hamaca->recetas->firstWhere('estado', 'activa'); $draft = $hamaca->recetas->firstWhere('estado', 'borrador');
            return ['hamaca' => ['id' => $hamaca->id, 'nombre' => $hamaca->nombre, 'categoria' => $hamaca->categoria?->nombre, 'tamano' => $hamaca->tamano?->nombre, 'precio' => $hamaca->precio, 'colores' => $hamaca->colores->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()], 'receta_activa' => $active ? ['id' => $active->id, 'version' => (int) $active->version] : null, 'receta_borrador' => $draft ? ['id' => $draft->id, 'version' => (int) $draft->version] : null, 'costo_produccion' => $active ? $this->costoService->calcularReceta($active)['resumen']['costo_produccion'] : null];
        });
        return response()->json(['data' => $data, 'meta' => ['current_page' => $hamacas->currentPage(), 'last_page' => $hamacas->lastPage(), 'per_page' => $hamacas->perPage(), 'total' => $hamacas->total()]]);
    }
}
