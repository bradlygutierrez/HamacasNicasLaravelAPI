<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\HamacaVarianteResource;
use App\Models\Foto;
use App\Models\HamacaVariante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HamacaVarianteController extends Controller
{
    public function index()
    {
        $variantes = HamacaVariante::with([
            'hamaca.categoria',
            'hamaca.tamano',
            'colores',
            'fotos',
            'inventarios.ubicacion',
            'inventarios.usuario',
        ])
            ->where('state', true)
            ->latest()
            ->paginate();

        return HamacaVarianteResource::collection($variantes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'hamaca_id' => 'required|integer|exists:hamacas,id',
            'nombre' => 'nullable|string|max:100',

            'color_ids' => 'required|array|min:1',
            'color_ids.*' => 'integer|exists:colores,id',

            'rutas' => 'nullable|array',
            'rutas.*' => 'nullable|string|max:255',

            'fotos' => 'nullable|array',
            'fotos.*' => 'image|max:4096',
        ]);

        $result = DB::transaction(function () use ($request, $validated) {
            $colorIds = $this->normalizeColorIds($validated['color_ids']);
            $composicionClave = $this->compositionKey($colorIds);

            $variante = HamacaVariante::where('hamaca_id', $validated['hamaca_id'])
                ->where('composicion_clave', $composicionClave)
                ->first();

            $wasCreated = false;

            if (!$variante) {
                $variante = new HamacaVariante();
                $variante->hamaca_id = $validated['hamaca_id'];
                $variante->composicion_clave = $composicionClave;
                $variante->state = true;
                $wasCreated = true;
            }

            $variante->nombre = $validated['nombre'] ?? $variante->nombre;
            $variante->state = true;
            $variante->save();

            $variante->colores()->sync($colorIds);

            $this->saveFotos($request, $variante, $validated);

            return [
                'was_created' => $wasCreated,
                'variante' => $variante->fresh([
                    'hamaca.categoria',
                    'hamaca.tamano',
                    'colores',
                    'fotos',
                    'inventarios.ubicacion',
                    'inventarios.usuario',
                ]),
            ];
        });

        return response()->json([
            'message' => $result['was_created']
                ? 'Variante creada correctamente.'
                : 'La variante ya existía. Se actualizó correctamente.',
            'data' => new HamacaVarianteResource($result['variante']),
        ], $result['was_created'] ? 201 : 200);
    }

    public function show(HamacaVariante $hamacaVariante)
    {
        return new HamacaVarianteResource(
            $hamacaVariante->load([
                'hamaca.categoria',
                'hamaca.tamano',
                'colores',
                'fotos',
                'inventarios.ubicacion',
                'inventarios.usuario',
            ])
        );
    }

    public function update(Request $request, HamacaVariante $hamacaVariante)
    {
        $validated = $request->validate([
            'hamaca_id' => 'sometimes|integer|exists:hamacas,id',
            'nombre' => 'nullable|string|max:100',
            'state' => 'sometimes|boolean',

            'color_ids' => 'sometimes|array|min:1',
            'color_ids.*' => 'integer|exists:colores,id',

            'rutas' => 'nullable|array',
            'rutas.*' => 'nullable|string|max:255',

            'fotos' => 'nullable|array',
            'fotos.*' => 'image|max:4096',
        ]);

        DB::transaction(function () use ($request, $validated, $hamacaVariante) {
            if (array_key_exists('hamaca_id', $validated)) {
                $hamacaVariante->hamaca_id = $validated['hamaca_id'];
            }

            if (array_key_exists('nombre', $validated)) {
                $hamacaVariante->nombre = $validated['nombre'];
            }

            if (array_key_exists('state', $validated)) {
                $hamacaVariante->state = (bool) $validated['state'];
            }

            if (array_key_exists('color_ids', $validated)) {
                $colorIds = $this->normalizeColorIds($validated['color_ids']);
                $composicionClave = $this->compositionKey($colorIds);

                $existing = HamacaVariante::where('hamaca_id', $hamacaVariante->hamaca_id)
                    ->where('composicion_clave', $composicionClave)
                    ->where('id', '!=', $hamacaVariante->id)
                    ->first();

                if ($existing) {
                    abort(422, 'Ya existe una variante con esa composición de colores.');
                }

                $hamacaVariante->composicion_clave = $composicionClave;
                $hamacaVariante->save();

                $hamacaVariante->colores()->sync($colorIds);
            } else {
                $hamacaVariante->save();
            }

            $this->saveFotos($request, $hamacaVariante, $validated);
        });

        return response()->json([
            'message' => 'Variante actualizada correctamente.',
            'data' => new HamacaVarianteResource(
                $hamacaVariante->fresh([
                    'hamaca.categoria',
                    'hamaca.tamano',
                    'colores',
                    'fotos',
                    'inventarios.ubicacion',
                    'inventarios.usuario',
                ])
            ),
        ]);
    }

    public function destroy(HamacaVariante $hamacaVariante)
    {
        $hamacaVariante->state = false;
        $hamacaVariante->save();

        return response()->json([
            'message' => 'Variante desactivada correctamente.',
        ]);
    }

    private function normalizeColorIds(array $colorIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $colorIds)));
        sort($ids);

        return $ids;
    }

    private function compositionKey(array $colorIds): string
    {
        return hash('sha256', implode(',', $this->normalizeColorIds($colorIds)));
    }

    private function saveFotos(Request $request, HamacaVariante $variante, array $validated): void
    {
        $rutas = collect($validated['rutas'] ?? [])
            ->map(fn ($ruta) => is_string($ruta) ? trim($ruta) : '')
            ->filter()
            ->unique()
            ->values();

        foreach ($rutas as $ruta) {
            $foto = Foto::create([
                'ruta' => $ruta,
            ]);

            $foto->variantes()->syncWithoutDetaching([$variante->id]);
        }

        $files = $request->file('fotos', []);

        if (!is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if (!$file) {
                continue;
            }

            $ruta = $file->store('fotos', 'public');

            $foto = Foto::create([
                'ruta' => $ruta,
            ]);

            $foto->variantes()->syncWithoutDetaching([$variante->id]);
        }
    }
}