<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventarioHamacaRequest;
use App\Http\Requests\TransferInventarioRequest;
use App\Http\Resources\V1\InventarioHamacaCollection;
use App\Http\Resources\V1\InventarioHamacaResource;
use App\Models\InventarioHamaca;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioHamacaController extends Controller
{
    public function __construct(private readonly InventarioService $service)
    {
    }

    public function index()
    {
        return new InventarioHamacaCollection(
            InventarioHamaca::with([
                'hamaca.categoria',
                'hamaca.tamano',
                'ubicacion',
                'usuario',
                'colores',
            ])->latest()->paginate()
        );
    }

    public function store(StoreInventarioHamacaRequest $request)
    {
        $inventario = $this->service->upsert($request->validated());

        return response()->json([
            'message' => 'Inventario creado correctamente',
            'data' => new InventarioHamacaResource($inventario),
        ], 201);
    }

    public function show(InventarioHamaca $inventarioHamaca)
    {
        return new InventarioHamacaResource(
            $inventarioHamaca->load(['hamaca.categoria', 'hamaca.tamano', 'ubicacion', 'usuario', 'colores'])
        );
    }

    public function update(StoreInventarioHamacaRequest $request, InventarioHamaca $inventarioHamaca)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($inventarioHamaca, $validated) {
            $inventarioHamaca->update([
                'hamaca_id' => $validated['hamaca_id'],
                'usuario_id' => $validated['usuario_id'],
                'ubicacion_id' => $validated['ubicacion_id'],
                'cantidad' => $validated['cantidad'],
                'composicion_clave' => $this->service->compositionKey($validated['color_ids']),
            ]);

            $inventarioHamaca->colores()->sync($validated['color_ids']);
        });

        return response()->json([
            'message' => 'Inventario actualizado correctamente',
            'data' => new InventarioHamacaResource(
                $inventarioHamaca->load(['hamaca.categoria', 'hamaca.tamano', 'ubicacion', 'usuario', 'colores'])
            ),
        ]);
    }

    public function destroy(InventarioHamaca $inventarioHamaca)
    {
        $inventarioHamaca->delete();

        return response()->json([
            'message' => 'Inventario eliminado correctamente.',
        ]);
    }

    public function transfer(TransferInventarioRequest $request)
    {
        $validated = $request->validated();

        $inventario = $this->service->transfer(
            $validated['inventario_hamaca_id'],
            $validated['cantidad'],
            $validated['ubicacion_destino_id'] ?? null
        );

        return response()->json([
            'message' => 'Transferencia realizada correctamente.',
            'data' => new InventarioHamacaResource($inventario),
        ]);
    }
}
