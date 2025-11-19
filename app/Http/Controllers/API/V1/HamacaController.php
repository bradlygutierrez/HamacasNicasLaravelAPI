<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Hamaca;
use Illuminate\Http\Request;
use App\Http\Resources\V1\HamacaResource;
use App\Http\Resources\V1\HamacaCollection;

class HamacaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Retorna todas las hamacas
       return new HamacaCollection(Hamaca::latest()->paginate());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validación simple
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string',
            'categoria_id' => 'required|integer',
            'ubicacion_id' => 'required|integer',
            'tamano_id' => 'required|integer',
            'cantidad' => 'required|integer',
            'precio' => 'required|numeric',
        ]);

        $hamaca = Hamaca::create($validated);

        return response()->json([
            'message' => 'Hamaca creada correctamente',
            'data' => new HamacaResource($hamaca)
        ], 201);
        
    }

    /**
     * Display the specified resource.
     */
    public function show(Hamaca $hamaca)
    {
        return new HamacaResource($hamaca);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Hamaca $hamaca)
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:100',
            'descripcion' => 'nullable|string',
            'categoria_id' => 'sometimes|integer',
            'ubicacion_id' => 'sometimes|integer',
            'tamano_id' => 'sometimes|integer',
            'cantidad' => 'sometimes|integer',
            'precio' => 'sometimes|numeric',
        ]);

        $hamaca->update($validated);

        return response()->json([
            'message' => 'Hamaca actualizada correctamente',
            'data' => new HamacaResource($hamaca)
        ], 201);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Hamaca $hamaca)
    {
        $hamaca->delete();

        return response()->json(['message' => 'Hamaca eliminada correctamente.']);
    }

    public function addColor(Request $request, \App\Models\Hamaca $hamaca)
    {
        $request->validate([
            'color_id' => 'required|exists:colores,id'
        ]);

        // evita duplicados automáticamente
        $hamaca->colores()->syncWithoutDetaching([$request->color_id]);

        return response()->json(['message' => 'Color agregado']);
    }

    public function removeColor(\App\Models\Hamaca $hamaca, $colorId)
    {
        $hamaca->colores()->detach($colorId);

        return response()->json(['message' => 'Color eliminado']);
    }

    public function addUsuario(Request $request, \App\Models\Hamaca $hamaca)
    {
        $request->validate([
            'usuario_id' => 'required|exists:usuarios,id'
        ]);

        $hamaca->usuarios()->syncWithoutDetaching([$request->usuario_id]);

        return response()->json(['message' => 'Usuario asignado a la hamaca']);
    }

    public function removeUsuario(\App\Models\Hamaca $hamaca, $usuarioId)
    {
        $hamaca->usuarios()->detach($usuarioId);

        return response()->json(['message' => 'Usuario desasignado de la hamaca']);
    }
}
