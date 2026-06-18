<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PantallaPermisoRolCollection;
use App\Http\Resources\V1\PantallaPermisoRolResource;
use App\Models\PantallaPermisoRol;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PantallaPermisoRolController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'rol' => ['nullable', Rule::in(PantallaPermisoRol::ROLES)],
            'pantalla_id' => 'nullable|integer|exists:pantallas,id',
            'permiso_id' => 'nullable|integer|exists:permisos,id',
        ]);

        $query = PantallaPermisoRol::with(['pantalla', 'permiso'])
            ->when($validated['rol'] ?? null, fn ($query, string $rol) => $query->where('rol', $rol))
            ->when($validated['pantalla_id'] ?? null, fn ($query, int $pantallaId) => $query->where('pantalla_id', $pantallaId))
            ->when($validated['permiso_id'] ?? null, fn ($query, int $permisoId) => $query->where('permiso_id', $permisoId))
            ->orderBy('rol')
            ->orderBy('pantalla_id')
            ->orderBy('permiso_id');

        return new PantallaPermisoRolCollection($query->paginate(10));
    }

    public function current(Request $request)
    {
        $accesos = PantallaPermisoRol::with(['pantalla', 'permiso'])
            ->where('rol', $request->user()->rol)
            ->whereHas('pantalla', fn ($query) => $query->where('state', true))
            ->orderBy('pantalla_id')
            ->orderBy('permiso_id')
            ->get();

        return new PantallaPermisoRolCollection($accesos);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pantalla_id' => 'required|integer|exists:pantallas,id',
            'permiso_id' => [
                'required',
                'integer',
                'exists:permisos,id',
                Rule::unique('pantalla_permiso_roles', 'permiso_id')
                    ->where('pantalla_id', $request->integer('pantalla_id'))
                    ->where('rol', $request->input('rol')),
            ],
            'rol' => ['required', Rule::in(PantallaPermisoRol::ROLES)],
        ]);

        $acceso = PantallaPermisoRol::create($validated)->load(['pantalla', 'permiso']);

        return (new PantallaPermisoRolResource($acceso))->response()->setStatusCode(201);
    }

    public function show(PantallaPermisoRol $pantallaPermisoRole)
    {
        return new PantallaPermisoRolResource($pantallaPermisoRole->load(['pantalla', 'permiso']));
    }

    public function update(Request $request, PantallaPermisoRol $pantallaPermisoRole)
    {
        $validated = $request->validate([
            'pantalla_id' => 'sometimes|integer|exists:pantallas,id',
            'permiso_id' => 'sometimes|integer|exists:permisos,id',
            'rol' => ['sometimes', Rule::in(PantallaPermisoRol::ROLES)],
        ]);

        $pantallaId = $validated['pantalla_id'] ?? $pantallaPermisoRole->pantalla_id;
        $permisoId = $validated['permiso_id'] ?? $pantallaPermisoRole->permiso_id;
        $rol = $validated['rol'] ?? $pantallaPermisoRole->rol;

        $exists = PantallaPermisoRol::where('pantalla_id', $pantallaId)
            ->where('permiso_id', $permisoId)
            ->where('rol', $rol)
            ->whereKeyNot($pantallaPermisoRole->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'La combinación pantalla, permiso y rol ya existe.',
            ], 422);
        }

        $pantallaPermisoRole->update($validated);

        return new PantallaPermisoRolResource($pantallaPermisoRole->load(['pantalla', 'permiso']));
    }

    public function destroy(PantallaPermisoRol $pantallaPermisoRole)
    {
        $pantallaPermisoRole->delete();

        return response()->json(['message' => 'Acceso eliminado correctamente.']);
    }
}
