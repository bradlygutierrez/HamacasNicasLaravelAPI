<?php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Color;
use App\Http\Resources\V1\ColorResource;
use App\Http\Resources\V1\ColorCollection;

class ColorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return new ColorCollection(Color::latest()->paginate());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
        ]);

        $colore = Color::create($validated);
        return response()->json([
            'message' => 'Color creada correctamente',
            'data' => new ColorResource($colore)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Color $colore)
    {
        return new ColorResource($colore);    
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Color $colore)
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:100',
        ]);

        $colore->update($validated);
        return response()->json([
            'message' => 'Color creada correctamente',
            'data' => new ColorResource($colore)
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Color $colore)
    {
        $colore->delete();
        return response()->json([
            'message' => 'Color eliminada correctamente'
        ], 200);
    }
}
