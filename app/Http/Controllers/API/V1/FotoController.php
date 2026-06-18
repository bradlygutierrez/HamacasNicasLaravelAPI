<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\FotoCollection;
use App\Http\Resources\V1\FotoResource;
use App\Models\Foto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FotoController extends Controller
{
    public function index()
    {
        return new FotoCollection(
            Foto::with('hamacas')->latest()->paginate()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ruta' => 'required|string|max:255',
            'hamaca_ids' => 'required|array|min:1',
            'hamaca_ids.*' => 'integer|exists:hamacas,id',
        ]);

        $foto = DB::transaction(function () use ($validated) {
            $foto = Foto::create([
                'ruta' => $validated['ruta'],
            ]);

            $foto->hamacas()->sync($validated['hamaca_ids']);

            return $foto->load('hamacas');
        });

        return response()->json([
            'message' => 'Foto creada correctamente.',
            'data' => new FotoResource($foto),
        ], 201);
    }

    public function show(Foto $foto)
    {
        return new FotoResource($foto->load('hamacas'));
    }

    public function update(Request $request, Foto $foto)
    {
        $validated = $request->validate([
            'ruta' => 'sometimes|string|max:255',
            'hamaca_ids' => 'sometimes|array|min:1',
            'hamaca_ids.*' => 'integer|exists:hamacas,id',
        ]);

        DB::transaction(function () use ($validated, $foto) {
            if (array_key_exists('ruta', $validated)) {
                $foto->update(['ruta' => $validated['ruta']]);
            }

            if (array_key_exists('hamaca_ids', $validated)) {
                $foto->hamacas()->sync($validated['hamaca_ids']);
            }
        });

        return response()->json([
            'message' => 'Foto actualizada correctamente.',
            'data' => new FotoResource($foto->load('hamacas')),
        ]);
    }

    public function destroy(Foto $foto)
    {
        $foto->delete();

        return response()->json([
            'message' => 'Foto eliminada correctamente.',
        ]);
    }
}
