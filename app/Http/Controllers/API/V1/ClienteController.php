<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ClienteResource;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $clients = Cliente::query()
            ->where('state', true)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%' . $request->string('search') . '%';
                $query->where(fn ($q) => $q
                    ->where('nombre', 'like', $term)
                    ->orWhere('ruc', 'like', $term)
                    ->orWhere('telefono', 'like', $term)
                    ->orWhere('correo', 'like', $term));
            })
            ->orderBy('nombre')
            ->paginate($perPage);

        return ClienteResource::collection($clients);
    }

    public function show(Cliente $cliente): ClienteResource
    {
        return new ClienteResource($cliente);
    }

    public function store(Request $request)
    {
        $cliente = Cliente::create($this->validated($request));

        return (new ClienteResource($cliente))->response()->setStatusCode(201);
    }

    public function update(Request $request, Cliente $cliente): ClienteResource
    {
        $cliente->update($this->validated($request, $cliente));

        return new ClienteResource($cliente->fresh());
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->update(['state' => false]);

        return response()->json(['message' => 'Cliente desactivado correctamente.']);
    }

    private function validated(Request $request, ?Cliente $cliente = null): array
    {
        return $request->validate([
            'nombre' => [$cliente ? 'sometimes' : 'required', 'string', 'max:150'],
            'ruc' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:150', Rule::unique('clientes', 'correo')->ignore($cliente?->id)],
        ]);
    }
}
