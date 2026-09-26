<?php

namespace App\Http\Controllers\API\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\HamacaCollection;
use App\Http\Resources\V1\HamacaResource;
use App\Models\Foto;
use App\Models\Hamaca;
use App\Services\HamacaNameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HamacaController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $term = '%' . $request->string('search') . '%';
        $query = Hamaca::with(['categoria', 'tamano', 'colores', 'fotos'])
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($term) {
                $q->where('nombre', 'like', $term)->orWhereHas('categoria', fn ($c) => $c->where('nombre', 'like', $term))
                    ->orWhereHas('tamano', fn ($s) => $s->where('nombre', 'like', $term))
                    ->orWhereHas('colores', fn ($c) => $c->where('nombre', 'like', $term));
            }))->latest();
        return new HamacaCollection($query->paginate($perPage));
    }

    public function getHamacasWithDetails()
    {
        return new HamacaCollection(Hamaca::with(['categoria', 'tamano', 'colores', 'fotos', 'inventarios.usuario', 'inventarios.ubicacion'])->latest()->paginate());
    }

    public function store(Request $request, HamacaNameService $names)
    {
        $data = $request->validate([
            'nombre' => 'nullable|string|max:150', 'descripcion' => 'nullable|string',
            'categoria_id' => 'required|integer|exists:categorias,id', 'tamano_id' => 'required|integer|exists:tamanos,id',
            'precio' => 'required|numeric|min:0', 'color_ids' => 'required|array|min:1', 'color_ids.*' => 'integer|distinct|exists:colores,id',
            'rutas' => 'nullable|array', 'rutas.*' => 'nullable|string|max:255', 'fotos' => 'nullable|array', 'fotos.*' => 'image|max:4096',
        ]);
        $hamaca = DB::transaction(function () use ($data, $request, $names) {
            $colors = array_values(array_unique(array_map('intval', $data['color_ids'])));
            $name = trim((string) ($data['nombre'] ?? '')) ?: $names->suggest((int) $data['categoria_id'], (int) $data['tamano_id'], $colors);
            $hamaca = Hamaca::create(collect($data)->only(['descripcion', 'categoria_id', 'tamano_id', 'precio'])->all() + ['nombre' => $name]);
            $hamaca->colores()->sync($colors);
            $this->syncPhotos($hamaca, $data['rutas'] ?? [], $request->file('fotos', []));
            return $hamaca->load(['categoria', 'tamano', 'colores', 'fotos']);
        });
        return (new HamacaResource($hamaca))->response()->setStatusCode(201);
    }

    public function show(Hamaca $hamaca)
    {
        return new HamacaResource($hamaca->load(['categoria', 'tamano', 'colores', 'fotos', 'inventarios.usuario', 'inventarios.ubicacion']));
    }

    public function update(Request $request, Hamaca $hamaca, HamacaNameService $names)
    {
        $data = $request->validate([
            'nombre' => 'sometimes|nullable|string|max:150', 'descripcion' => 'sometimes|nullable|string',
            'categoria_id' => 'sometimes|integer|exists:categorias,id', 'tamano_id' => 'sometimes|integer|exists:tamanos,id',
            'precio' => 'sometimes|numeric|min:0', 'color_ids' => 'sometimes|array|min:1', 'color_ids.*' => 'integer|distinct|exists:colores,id',
            'rutas' => 'nullable|array', 'rutas.*' => 'nullable|string|max:255', 'fotos' => 'nullable|array', 'fotos.*' => 'image|max:4096',
        ]);
        $hamaca = DB::transaction(function () use ($data, $request, $hamaca, $names) {
            $hamaca = Hamaca::query()->lockForUpdate()->findOrFail($hamaca->id);
            $hasHistory = $hamaca->recetas()->exists() || $hamaca->inventarios()->exists() || $hamaca->detalleFacturas()->exists()
                || DB::table('proforma_detalles')->where('hamaca_id', $hamaca->id)->exists()
                || DB::table('pedido_detalles')->where('hamaca_id', $hamaca->id)->exists();
            if ($hasHistory && ((isset($data['categoria_id']) && (int) $data['categoria_id'] !== (int) $hamaca->categoria_id)
                || (isset($data['tamano_id']) && (int) $data['tamano_id'] !== (int) $hamaca->tamano_id))) {
                throw new BusinessRuleException('La clasificación de una hamaca con historial no puede cambiarse. Creá una nueva hamaca.', [], 422);
            }
            if (array_key_exists('color_ids', $data)) {
                if ($hasHistory && $hamaca->colores()->pluck('colores.id')->sort()->values()->all() !== collect($data['color_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all()) {
                    throw new BusinessRuleException('La hamaca ya tiene historial productivo o comercial. Creá una nueva hamaca para otra combinación de colores.', [], 422);
                }
                $hamaca->colores()->sync($data['color_ids']);
            }
            $attributes = collect($data)->only(['nombre', 'descripcion', 'categoria_id', 'tamano_id', 'precio'])->all();
            if (array_key_exists('nombre', $data) && trim((string) $data['nombre']) === '') {
                $attributes['nombre'] = $names->suggest((int) ($data['categoria_id'] ?? $hamaca->categoria_id), (int) ($data['tamano_id'] ?? $hamaca->tamano_id), $data['color_ids'] ?? $hamaca->colores()->pluck('colores.id')->all());
            }
            $hamaca->update($attributes);
            if (array_key_exists('rutas', $data) || $request->hasFile('fotos')) $this->syncPhotos($hamaca, $data['rutas'] ?? [], $request->file('fotos', []), false);
            return $hamaca->fresh()->load(['categoria', 'tamano', 'colores', 'fotos']);
        });
        return new HamacaResource($hamaca);
    }

    public function destroy(Hamaca $hamaca)
    {
        $hamaca->delete();
        return response()->json(['message' => 'Hamaca archivada correctamente.']);
    }

    public function getMonthlyInventory()
    {
        return response()->json(['total' => DB::table('inventario_hamacas')->sum('cantidad')]);
    }

    private function syncPhotos(Hamaca $hamaca, array $routes, array $files, bool $replace = true): void
    {
        $photoIds = [];
        foreach ($routes as $route) if (trim((string) $route) !== '') $photoIds[] = Foto::firstOrCreate(['ruta' => trim($route)])->id;
        foreach ($files as $file) $photoIds[] = Foto::create(['ruta' => $file->store('fotos', 'public')])->id;
        if ($replace) $hamaca->fotos()->sync($photoIds); elseif ($photoIds !== []) $hamaca->fotos()->syncWithoutDetaching($photoIds);
    }
}
