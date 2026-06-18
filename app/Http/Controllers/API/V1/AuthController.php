<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'correo' => 'required|email',
            'password' => 'required|string',
        ]);

        $usuario = Usuario::where('correo', $credentials['correo'])->first();

        if (!$usuario || !Hash::check($credentials['password'], $usuario->password)) {
            return response()->json([
                'message' => 'Credenciales inválidas'
            ], 401);
        }

        if (!$usuario->state) {
            return response()->json([
                'message' => 'Usuario inactivo'
            ], 403);
        }

        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UsuarioResource($usuario),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente'
        ]);
    }

    public function me(Request $request)
    {
        return new UsuarioResource($request->user());
    }
}
