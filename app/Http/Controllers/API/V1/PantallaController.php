<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PantallaCollection;
use App\Http\Resources\V1\PantallaResource;
use App\Models\Pantalla;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PantallaController extends Controller
{
    public function index()
    {
        return new PantallaCollection(
            Pantalla::with('permisos')->orderBy('orden')->orderBy('nombre')->paginate(10)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:pantallas,slug',
            'descripcion' => 'nullable|string',
            'ruta' => 'nullable|string|max:255',
            'icono' => 'nullable|string|max:100',
            'orden' => 'nullable|integer|min:0',
            'state' => 'nullable|boolean',
        ]);

        $pantalla = Pantalla::create($validated);

        return (new PantallaResource($pantalla))->response()->setStatusCode(201);
    }

    public function show(Pantalla $pantalla)
    {
        return new PantallaResource($pantalla->load('permisos'));
    }

    public function update(Request $request, Pantalla $pantalla)
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:100',
            'slug' => ['sometimes', 'string', 'max:100', Rule::unique('pantallas', 'slug')->ignore($pantalla->id)],
            'descripcion' => 'nullable|string',
            'ruta' => 'nullable|string|max:255',
            'icono' => 'nullable|string|max:100',
            'orden' => 'nullable|integer|min:0',
            'state' => 'nullable|boolean',
        ]);

        $pantalla->update($validated);

        return new PantallaResource($pantalla);
    }

    public function destroy(Pantalla $pantalla)
    {
        $pantalla->delete();

        return response()->json(['message' => 'Pantalla eliminada correctamente.']);
    }
}
