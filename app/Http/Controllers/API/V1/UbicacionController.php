<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Resources\V1\UbicacionCollection;
use App\Http\Resources\V1\UbicacionResource;

use App\Models\Ubicacion;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UbicacionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return new UbicacionCollection(Ubicacion::latest()->paginate());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $ubicacion = Ubicacion::create($validated);

        return response()->json([
            'message' => 'Ubicación creada exitosamente',
            'data' => new UbicacionResource($ubicacion)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Ubicacion $ubicacion)
    {
        return new UbicacionResource($ubicacion);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Ubicacion $ubicacion)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'sometimes|required|string|max:255',
                'descripcion' => 'sometimes|nullable|string',
            ]);

            $ubicacion->update($validated);

            return response()->json([
                'message' => 'Ubicación actualizada exitosamente',
                'data' => new UbicacionResource($ubicacion)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la ubicación',
                'errors' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ubicacion $ubicacion)
    {
        $ubicacion->delete();

        return response()->json([
            'message' => 'Ubicación eliminada exitosamente'
        ], 200);
    }
}
