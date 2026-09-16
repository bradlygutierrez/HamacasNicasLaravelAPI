<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function loginInfo(Request $request)
    {
        if ($request->expectsJson()) {
            return response()
                ->json([
                    'message' => 'La ruta de login solo acepta POST.',
                ], 405)
                ->header('Allow', 'POST');
        }

        return redirect()->away(config('app.frontend_url'));
    }

    public function csrfToken(Request $request)
    {
        return response()->json([
            'csrf_token' => $request->session()->token(),
        ]);
    }

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

        if ($request->hasSession()) {
            Auth::guard('web')->login($usuario);
            $request->session()->regenerate();

            return response()->json([
                'message' => 'Login exitoso',
                'user' => new UsuarioResource($usuario),
            ]);
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
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Sesión cerrada correctamente'
        ]);
    }

    public function me(Request $request)
    {
        return new UsuarioResource($request->user());
    }
}
