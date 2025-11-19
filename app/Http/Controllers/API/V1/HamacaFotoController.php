<?php

namespace App\Http\Controllers\API\V1;


use App\Models\HamacaFoto;
use App\Http\Resources\V1\HamacaFotoResource;
use App\Http\Resources\V1\HamacaFotoCollection;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HamacaFotoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        
        return new HamacaFotoCollection(HamacaFoto::latest()->paginate());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'hamaca_id' => 'required|exists:hamacas,id',
            'ruta_foto' => 'required|string|max:255',
        ]);

        $fotos_hamaca = HamacaFoto::create($validatedData);

        return response()->json([
            'message' => 'Foto de hamaca creada correctamente',
            'data' => new HamacaFotoResource($fotos_hamaca)
        ], 201);
    }

    public function show(HamacaFoto $fotos_hamaca)
    {
        return new HamacaFotoResource($fotos_hamaca);
    }

    public function update(Request $request, HamacaFoto $fotos_hamaca)
    {
        $validatedData = $request->validate([
            'hamaca_id' => 'sometimes|exists:hamacas,id',
            'ruta_foto' => 'sometimes|string|max:255',
        ]);

        $fotos_hamaca->update($validatedData);

        return response()->json([
            'message' => 'Foto de hamaca actualizada correctamente',
            'data' => new HamacaFotoResource($fotos_hamaca)
        ], 200);
    }

    public function destroy(HamacaFoto $fotos_hamaca)
    {
        $fotos_hamaca->delete();

        return response()->json([
            'message' => 'Foto de hamaca eliminada correctamente'
        ], 200);
    }
}
