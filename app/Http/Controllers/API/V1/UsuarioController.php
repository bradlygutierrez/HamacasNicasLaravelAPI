<?php

namespace App\Http\Controllers\API\V1;

use Illuminate\Support\Facades\Hash;
use App\Http\Resources\V1\UsuarioResource;
use App\Http\Resources\V1\UsuarioCollection;
use App\Models\Usuario;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return new UsuarioCollection(Usuario::where('state', true)->latest()->paginate(10));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'correo' => 'required|string|email|max:150|unique:usuarios,correo',
            'password' => 'required|string|min:6',
            'foto' => 'nullable|image|max:2048',
            'rol' => 'nullable|in:admin,vendedor,almacenista,socio',
        ]);

        // Manejar carga de foto si se proporciona
        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('usuarios', 'public');
            $validated['foto'] = $path;
        }

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
            'correo' => ['sometimes', 'string', 'email', 'max:150', Rule::unique('usuarios', 'correo')->ignore($usuario->id)],
            'password' => 'sometimes|string|min:6',
            'foto' => 'nullable|image|max:2048',
            'rol' => 'nullable|in:admin,vendedor,almacenista,socio',
        ]);

        // Manejar carga de foto si se proporciona
        if ($request->hasFile('foto')) {
            if ($usuario->foto) {
                Storage::disk('public')->delete($usuario->foto);
            };
            $path = $request->file('foto')->store('usuarios', 'public');
            $validated['foto'] = $path;
        }

        $usuario->update($validated);

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'data' => new UsuarioResource($usuario)
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Usuario $usuario)
    {
        //Este condicional elimina la foto del disk si el el usuario tiene una foto asociada antes de eliminar el registro del usuario en la base de datos. Esto es importante para evitar dejar archivos huérfanos en el almacenamiento después de que el usuario haya sido eliminado.
        if ($usuario->foto) {
            Storage::disk('public')->delete($usuario->foto);
        }

        $usuario->update(['state' => false]);

        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }
}
