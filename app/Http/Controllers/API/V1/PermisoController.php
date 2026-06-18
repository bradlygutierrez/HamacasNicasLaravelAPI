<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PermisoCollection;
use App\Http\Resources\V1\PermisoResource;
use App\Models\Permiso;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermisoController extends Controller
{
    public function index()
    {
        return new PermisoCollection(
            Permiso::with('pantallas')->orderBy('nombre')->paginate(10)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:permisos,slug',
            'descripcion' => 'nullable|string',
        ]);

        $permiso = Permiso::create($validated);

        return (new PermisoResource($permiso))->response()->setStatusCode(201);
    }

    public function show(Permiso $permiso)
    {
        return new PermisoResource($permiso->load('pantallas'));
    }

    public function update(Request $request, Permiso $permiso)
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:100',
            'slug' => ['sometimes', 'string', 'max:100', Rule::unique('permisos', 'slug')->ignore($permiso->id)],
            'descripcion' => 'nullable|string',
        ]);

        $permiso->update($validated);

        return new PermisoResource($permiso);
    }

    public function destroy(Permiso $permiso)
    {
        $permiso->delete();

        return response()->json(['message' => 'Permiso eliminado correctamente.']);
    }
}
