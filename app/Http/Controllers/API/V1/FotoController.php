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
        DB::transaction(function () use ($request, $data, $foto) { if ($request->hasFile('foto')) $foto->update(['ruta' => $request->file('foto')->store('fotos', 'public')]); elseif (isset($data['ruta'])) $foto->update(['ruta' => $data['ruta']]); if (array_key_exists('hamaca_ids', $data)) $foto->hamacas()->sync($data['hamaca_ids']); });
        return new FotoResource($foto->fresh()->load('hamacas'));
    }
    public function destroy(Foto $foto) { DB::transaction(fn () => $foto->hamacas()->detach()); $foto->delete(); return response()->json(['message' => 'Foto eliminada correctamente.']); }
    public function copySource(Request $request, Foto $foto) { return response()->json(['data' => $foto->load('hamacas')]); }
}
