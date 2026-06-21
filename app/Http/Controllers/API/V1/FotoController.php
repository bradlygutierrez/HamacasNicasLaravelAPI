<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\FotoCollection;
use App\Http\Resources\V1\FotoResource;
use App\Models\Foto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FotoController extends Controller
{
    public function index()
    {
        return new FotoCollection(
            Foto::with([
                'hamacas',
                'variantes.hamaca',
                'variantes.colores',
            ])->latest()->paginate()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ruta' => 'required_without:foto|string|max:255',
            'foto' => 'required_without:ruta|image|max:4096',

            'hamaca_variante_ids' => 'required_without:hamaca_ids|array|min:1',
            'hamaca_variante_ids.*' => 'integer|exists:hamaca_variantes,id',

            'hamaca_ids' => 'nullable|array|min:1',
            'hamaca_ids.*' => 'integer|exists:hamacas,id',
        ]);

        $foto = DB::transaction(function () use ($validated, $request) {
            $ruta = $validated['ruta'] ?? null;

            if ($request->hasFile('foto')) {
                $ruta = $request->file('foto')->store('fotos', 'public');
            }

            $foto = Foto::create([
                'ruta' => $ruta,
            ]);

            if (!empty($validated['hamaca_variante_ids'])) {
                $foto->variantes()->sync($validated['hamaca_variante_ids']);
            }

            // Compatibilidad temporal con el flujo viejo.
            if (!empty($validated['hamaca_ids'])) {
                $foto->hamacas()->sync($validated['hamaca_ids']);
            }

            return $foto->load([
                'variantes.hamaca',
                'variantes.colores',
                'hamacas',
            ]);
        });

        return response()->json([
            'message' => 'Foto creada correctamente.',
            'data' => new FotoResource($foto),
        ], 201);
    }

    public function show(Foto $foto)
    {
        return new FotoResource(
            $foto->load([
                'variantes.hamaca',
                'variantes.colores',
                'hamacas',
            ])
        );
    }

    public function update(Request $request, Foto $foto)
    {
        $validated = $request->validate([
            'ruta' => 'sometimes|string|max:255',
            'foto' => 'sometimes|image|max:4096',

            'hamaca_variante_ids' => 'sometimes|array|min:1',
            'hamaca_variante_ids.*' => 'integer|exists:hamaca_variantes,id',

            'hamaca_ids' => 'sometimes|array|min:1',
            'hamaca_ids.*' => 'integer|exists:hamacas,id',
        ]);

        DB::transaction(function () use ($validated, $request, $foto) {
            if ($request->hasFile('foto')) {
                $this->deleteLocalFile($foto->ruta);

                $ruta = $request->file('foto')->store('fotos', 'public');

                $foto->update([
                    'ruta' => $ruta,
                ]);
            } elseif (array_key_exists('ruta', $validated)) {
                $this->deleteLocalFile($foto->ruta);

                $foto->update([
                    'ruta' => $validated['ruta'],
                ]);
            }

            if (array_key_exists('hamaca_variante_ids', $validated)) {
                $foto->variantes()->sync($validated['hamaca_variante_ids']);
            }

            if (array_key_exists('hamaca_ids', $validated)) {
                $foto->hamacas()->sync($validated['hamaca_ids']);
            }
        });

        return response()->json([
            'message' => 'Foto actualizada correctamente.',
            'data' => new FotoResource(
                $foto->fresh()->load([
                    'variantes.hamaca',
                    'variantes.colores',
                    'hamacas',
                ])
            ),
        ]);
    }

    public function destroy(Foto $foto)
    {
        DB::transaction(function () use ($foto) {
            $this->deleteLocalFile($foto->ruta);

            $foto->variantes()->detach();
            $foto->hamacas()->detach();

            $foto->delete();
        });

        return response()->json([
            'message' => 'Foto eliminada correctamente.',
        ]);
    }

    private function deleteLocalFile(?string $ruta): void
    {
        if (!$ruta) {
            return;
        }

        if (str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://')) {
            return;
        }

        $path = $ruta;

        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function copySource(Foto $foto)
    {
        $ruta = $foto->ruta;

        if (str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://')) {
            return response()->json([
                'message' => 'Esta foto es una URL externa y no puede copiarse como archivo local desde este endpoint.',
            ], 422);
        }

        $path = $ruta;

        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (!Storage::disk('public')->exists($path)) {
            return response()->json([
                'message' => 'Archivo no encontrado.',
            ], 404);
        }

        $fullPath = Storage::disk('public')->path($path);
        $mime = mime_content_type($fullPath) ?: 'image/jpeg';
        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
