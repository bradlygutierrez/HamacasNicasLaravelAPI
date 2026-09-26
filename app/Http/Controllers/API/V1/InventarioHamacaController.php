<?php

namespace App\Http\Controllers\API\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventarioEntradaRequest;
use App\Http\Requests\StoreInventarioSalidaRequest;
use App\Http\Requests\TransferInventarioRequest;
use App\Http\Resources\V1\InventarioHamacaCollection;
use App\Http\Resources\V1\InventarioHamacaResource;
use App\Models\InventarioHamaca;
use App\Services\InventarioService;
use Illuminate\Http\Request;

class InventarioHamacaController extends Controller
{
    public function __construct(private readonly InventarioService $service) {}
    public function index(Request $request) { return new InventarioHamacaCollection(InventarioHamaca::with(['hamaca.categoria', 'hamaca.tamano', 'hamaca.fotos', 'hamaca.colores', 'ubicacion', 'usuario'])->latest()->paginate(min(max($request->integer('per_page', 15), 1), 100))); }
    public function show(InventarioHamaca $inventarioHamaca) { return new InventarioHamacaResource($inventarioHamaca->load(['hamaca.categoria', 'hamaca.tamano', 'hamaca.fotos', 'hamaca.colores', 'ubicacion', 'usuario'])); }
    public function destroy(InventarioHamaca $inventarioHamaca) { if ($inventarioHamaca->movimientos()->exists() || $inventarioHamaca->detalleFacturas()->exists()) throw new BusinessRuleException('El inventario tiene historial y no puede eliminarse físicamente.', ['inventario_hamaca_id' => ['El inventario tiene historial y no puede eliminarse físicamente.']]); $inventarioHamaca->delete(); return response()->json(['message' => 'Inventario eliminado correctamente.']); }
    public function entrada(StoreInventarioEntradaRequest $request) { $inventory = $this->service->entrada($request->validated(), $request->user()->id); return response()->json(['message' => 'Entrada registrada correctamente.', 'data' => new InventarioHamacaResource($inventory)], 201); }
    public function salida(StoreInventarioSalidaRequest $request) { $data = $request->validated(); $inventory = $this->service->salida($data['inventario_hamaca_id'], $data['cantidad'], $request->user()->id, $data['fecha'] ?? null); return response()->json(['message' => 'Salida registrada correctamente.', 'data' => new InventarioHamacaResource($inventory)]); }
    public function transfer(TransferInventarioRequest $request) { $data = $request->validated(); $inventory = $this->service->transfer($data['inventario_hamaca_id'], $data['cantidad'], $data['ubicacion_destino_id'], $request->user()->id, $data['fecha'] ?? null); return response()->json(['message' => 'Transferencia realizada correctamente.', 'data' => new InventarioHamacaResource($inventory)]); }
}
