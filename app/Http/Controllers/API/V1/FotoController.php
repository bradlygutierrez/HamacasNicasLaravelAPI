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
    public function index() { return new FotoCollection(Foto::with('hamacas')->latest()->paginate()); }
    public function store(Request $request)
    {
        $data = $request->validate(['ruta' => 'required_without:foto|string|max:255', 'foto' => 'required_without:ruta|image|max:4096', 'hamaca_ids' => 'required|array|min:1', 'hamaca_ids.*' => 'integer|exists:hamacas,id']);
        $foto = DB::transaction(function () use ($request, $data) { $foto = Foto::create(['ruta' => $request->hasFile('foto') ? $request->file('foto')->store('fotos', 'public') : $data['ruta']]); $foto->hamacas()->sync($data['hamaca_ids']); return $foto->load('hamacas'); });
        return response()->json(['message' => 'Foto creada correctamente.', 'data' => new FotoResource($foto)], 201);
    }
    public function show(Foto $foto) { return new FotoResource($foto->load('hamacas')); }
    public function update(Request $request, Foto $foto)
    {
        $data = $request->validate(['ruta' => 'sometimes|string|max:255', 'foto' => 'sometimes|image|max:4096', 'hamaca_ids' => 'sometimes|array|min:1', 'hamaca_ids.*' => 'integer|exists:hamacas,id']);
        DB::transaction(function () use ($request, $data, $foto): void {
            if ($request->hasFile('foto')) {
                $this->deleteLocalFile($foto->ruta);
                $foto->update(['ruta' => $request->file('foto')->store('fotos', 'public')]);
            } elseif (array_key_exists('ruta', $data)) {
                $this->deleteLocalFile($foto->ruta);
                $foto->update(['ruta' => $data['ruta']]);
            }

            if (array_key_exists('hamaca_ids', $data)) {
                $foto->hamacas()->sync($data['hamaca_ids']);
            }
        });
        return new FotoResource($foto->fresh()->load('hamacas'));
    }
    public function destroy(Foto $foto)
    {
        DB::transaction(function () use ($foto): void {
            $this->deleteLocalFile($foto->ruta);
            $foto->hamacas()->detach();
            $foto->delete();
        });

        return response()->json(['message' => 'Foto eliminada correctamente.']);
    }

    public function copySource(Foto $foto)
    {
        $ruta = $foto->ruta;
        if (str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://')) {
            return response()->json(['message' => 'Esta foto es una URL externa y no puede copiarse como archivo local desde este endpoint.'], 422);
        }

        $path = $this->localPath($ruta);
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Archivo no encontrado.'], 404);
        }

        $fullPath = Storage::disk('public')->path($path);

        return response()->file($fullPath, [
            'Content-Type' => mime_content_type($fullPath) ?: 'application/octet-stream',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    private function deleteLocalFile(?string $ruta): void
    {
        if (!$ruta || str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://')) {
            return;
        }

        $path = $this->localPath($ruta);
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function localPath(string $ruta): string
    {
        $path = ltrim($ruta, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path;
    }
}
