<?php

namespace App\Http\Controllers\API\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventarioEntradaRequest;
use App\Http\Requests\StoreInventarioHamacaRequest;
use App\Http\Requests\StoreInventarioSalidaRequest;
use App\Http\Requests\TransferInventarioRequest;
use App\Http\Resources\V1\InventarioHamacaCollection;
use App\Http\Resources\V1\InventarioHamacaResource;
use App\Models\HamacaVariante;
use App\Models\InventarioHamaca;
use App\Services\InventarioService;
use Illuminate\Support\Facades\DB;

class InventarioHamacaController extends Controller
{
    public function __construct(private readonly InventarioService $service) {}

    public function index(\Illuminate\Http\Request $request)
    {
        return new InventarioHamacaCollection(
            InventarioHamaca::with([
                'hamaca.categoria',
                'hamaca.tamano',
                'hamaca.fotos',
                'variante.colores',
                'variante.fotos',
                'ubicacion',
                'usuario',
                'colores',
            ])->latest()->paginate(min((int) $request->input('per_page', 15), 100))
        );
    }

    /*public function store(StoreInventarioHamacaRequest $request)
    {
        $inventario = $this->service->upsert($request->validated());

        return response()->json([
            'message' => 'Inventario creado correctamente',
            'data' => new InventarioHamacaResource($inventario),
        ], 201);
    }*/

    public function show(InventarioHamaca $inventarioHamaca)
    {
        return new InventarioHamacaResource(
            $inventarioHamaca->load([
                'hamaca.categoria',
                'hamaca.tamano',
                'hamaca.fotos',
                'variante.colores',
                'variante.fotos',
                'ubicacion',
                'usuario',
                'colores',
            ])
        );
    }

    /*public function update(StoreInventarioHamacaRequest $request, InventarioHamaca $inventarioHamaca)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($inventarioHamaca, $validated) {
            if (!empty($validated['hamaca_variante_id'])) {
                $variante = HamacaVariante::with('colores')
                    ->lockForUpdate()
                    ->findOrFail($validated['hamaca_variante_id']);

                $colorIds = $variante->colores->pluck('id')->map(fn ($id) => (int) $id)->all();

                $inventarioHamaca->update([
                    'hamaca_id' => $variante->hamaca_id,
                    'hamaca_variante_id' => $variante->id,
                    'usuario_id' => $validated['usuario_id'],
                    'ubicacion_id' => $validated['ubicacion_id'],
                    'cantidad' => $validated['cantidad'],
                    'composicion_clave' => $variante->composicion_clave,
                ]);

                $inventarioHamaca->colores()->sync($colorIds);

                return;
            }

            $inventarioHamaca->update([
                'hamaca_id' => $validated['hamaca_id'],
                'hamaca_variante_id' => null,
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
                $inventarioHamaca->load([
                    'hamaca.categoria',
                    'hamaca.tamano',
                    'hamaca.fotos',
                    'variante.colores',
                    'variante.fotos',
                    'ubicacion',
                    'usuario',
                    'colores',
                ])
            ),
        ]);
    }*/

    public function destroy(InventarioHamaca $inventarioHamaca)
    {
        if ($inventarioHamaca->movimientos()->exists() || $inventarioHamaca->detalleFacturas()->exists()) {
            throw new BusinessRuleException('El inventario tiene historial y no puede eliminarse físicamente.', [
                'inventario_hamaca_id' => ['El inventario tiene historial y no puede eliminarse físicamente.'],
            ]);
        }

        $inventarioHamaca->delete();

        return response()->json([
            'message' => 'Inventario eliminado correctamente.',
        ]);
    }

    public function entrada(StoreInventarioEntradaRequest $request)
    {
        $inventario = $this->service->entrada(
            $request->validated(),
            $request->user()->id
        );

        return response()->json([
            'message' => 'Entrada registrada correctamente.',
            'data' => new InventarioHamacaResource($inventario),
        ], 201);
    }

    public function salida(StoreInventarioSalidaRequest $request)
    {
        $validated = $request->validated();

        $inventario = $this->service->salida(
            $validated['inventario_hamaca_id'],
            $validated['cantidad'],
            $request->user()->id,
            $validated['fecha'] ?? null
        );

        return response()->json([
            'message' => 'Salida registrada correctamente.',
            'data' => new InventarioHamacaResource($inventario),
        ]);
    }

    public function transfer(TransferInventarioRequest $request)
    {
        $validated = $request->validated();

        $inventario = $this->service->transfer(
            $validated['inventario_hamaca_id'],
            $validated['cantidad'],
            $validated['ubicacion_destino_id'],
            $request->user()->id,
            $validated['fecha'] ?? null
        );

        return response()->json([
            'message' => 'Transferencia realizada correctamente.',
            'data' => new InventarioHamacaResource($inventario),
        ]);
    }
}
