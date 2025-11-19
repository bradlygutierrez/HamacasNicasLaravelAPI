<?php

namespace App\Http\Controllers\API\V1;

use Illuminate\Support\Facades\Hash;
use App\Http\Resources\V1\UsuarioResource;
use App\Http\Resources\V1\UsuarioCollection;
use App\Models\Usuario;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return new UsuarioCollection(Usuario::latest()->paginate(10));

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'correo' => 'required|string|email|max:150|unique:usuarios,correo',
            'contraseña' => 'required|string|min:6',
            'rol' => 'nullable|in:admin,vendedor,almacenista,socio',
        ]);

        // Cifrar contraseña
        $validated['contraseña'] = Hash::make($validated['contraseña']);

        $usuario = Usuario::create($validated);

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'data' => new UsuarioResource($usuario)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Usuario $usuario)
    {
        return new UsuarioResource($usuario);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Usuario $usuario)
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:100',
            'correo' => 'sometimes|string|email|max:150|unique:usuarios,correo,' . $usuario->id,
            'contraseña' => 'sometimes|string|min:6',
            'rol' => 'nullable|in:admin,vendedor,almacenista,socio',
        ]);

        // Cifrar contraseña solo si viene en el request
        if (isset($validated['contraseña'])) {
            $validated['contraseña'] = Hash::make($validated['contraseña']);
        }

        $usuario->update($validated);

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'data' => new UsuarioResource($usuario)
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Usuario $usuario)
    {
        $usuario->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }
}
