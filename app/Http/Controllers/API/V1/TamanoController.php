<?php

namespace App\Http\Controllers\API\V1;
use App\Http\Resources\V1\TamanoCollection;
use App\Http\Resources\V1\TamanoResource;
use App\Models\Tamano;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TamanoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return new TamanoCollection(Tamano::latest()->paginate());
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

        $tamano = Tamano::create($validated);

        return response()->json([
            'message' => 'Tamano creado exitosamente',
            'data' => new TamanoResource($tamano)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Tamano $tamano)
    {
        return new TamanoResource($tamano);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tamano $tamano)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'sometimes|required|string|max:255',
                'descripcion' => 'sometimes|nullable|string',
            ]);

            $tamano->update($validated);
            return response()->json([
                'message' => 'Tamano actualizado exitosamente',
                'data' => new TamanoResource($tamano)
            ], 200);
        } catch (exec $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tamano $tamano)
    {
        $tamano->delete();
        return response()->json('Tamano eliminado exitosamente', 200);
    }
}
