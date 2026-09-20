<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConvertPedidoRequest;
use App\Http\Requests\PedidoLogisticsRequest;
use App\Http\Requests\PedidoMaterialRequest;
use App\Http\Requests\PedidoProcesoRequest;
use App\Http\Requests\PedidoStatusRequest;
use App\Http\Resources\V1\PedidoHistorialResource;
use App\Http\Resources\V1\PedidoMaterialResource;
use App\Http\Resources\V1\PedidoProcesoResource;
use App\Http\Resources\V1\PedidoResource;
use App\Models\Pedido;
use App\Models\PedidoMaterial;
use App\Models\PedidoProceso;
use App\Models\Proforma;
use App\Services\PedidoMaterialService;
use App\Services\PedidoProcesoService;
use App\Services\PedidoService;
use Illuminate\Http\Request;

class PedidoController extends Controller
{
    public function __construct(private readonly PedidoService $service, private readonly PedidoMaterialService $materials, private readonly PedidoProcesoService $processes) {}
    public function index(Request $request)
    {
        $query = Pedido::with('vendedor')->withCount(['materiales as materiales_total', 'procesos as procesos_total'])->withCount(['materiales as materiales_listos' => fn ($q) => $q->where('estado', 'listo'), 'procesos as procesos_completados' => fn ($q) => $q->where('estado', 'completado')])->when($request->user()->rol === 'vendedor', fn ($q) => $q->where('vendedor_id', $request->user()->id))->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('numero', 'like', '%' . $request->string('search') . '%')->orWhere('proforma_numero_snapshot', 'like', '%' . $request->string('search') . '%')->orWhere('nombre_cliente', 'like', '%' . $request->string('search') . '%')->orWhere('ruc', 'like', '%' . $request->string('search') . '%')))->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))->when($request->filled('vendedor_id') && $request->user()->rol !== 'vendedor', fn ($q) => $q->where('vendedor_id', $request->integer('vendedor_id')))->when($request->filled('cliente_id'), fn ($q) => $q->where('cliente_id', $request->integer('cliente_id')))->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_pedido', '>=', $request->date('desde')))->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_pedido', '<=', $request->date('hasta')))->latest(); $page = $query->paginate(min(max($request->integer('per_page', 15), 1), 100));
        return PedidoResource::collection($page);
    }
    public function show(Request $request, Pedido $pedido): PedidoResource { $this->assertView($pedido, $request); return new PedidoResource($pedido->load($this->relations())); }
    public function update(PedidoLogisticsRequest $request, Pedido $pedido): PedidoResource { $this->assertView($pedido, $request); return new PedidoResource($this->service->updateLogistics($pedido, $request->validated(), $request->user())); }
    public function status(PedidoStatusRequest $request, Pedido $pedido): PedidoResource { $this->assertView($pedido, $request); return new PedidoResource($this->service->changeStatus($pedido, $request->validated('estado'), $request->user(), $request->validated('comentario'))); }
    public function material(PedidoMaterialRequest $request, Pedido $pedido, PedidoMaterial $pedidoMaterial): PedidoMaterialResource { $this->assertView($pedido, $request); return new PedidoMaterialResource($this->materials->update($pedido, $pedidoMaterial, $request->validated(), $request->user())); }
    public function process(PedidoProcesoRequest $request, Pedido $pedido, PedidoProceso $pedidoProceso): PedidoProcesoResource { $this->assertView($pedido, $request); return new PedidoProcesoResource($this->processes->update($pedido, $pedidoProceso, $request->validated(), $request->user())); }
    public function history(Request $request, Pedido $pedido) { $this->assertView($pedido, $request); return PedidoHistorialResource::collection($pedido->historial()->latest()->get()); }
    private function assertView(Pedido $pedido, Request $request): void { if (!$this->service->canView($pedido, $request->user())) abort(403, 'No tenés acceso a este pedido.'); }
    private function relations(): array { return ['proforma','vendedor','detalles.servicios','servicios','materiales','procesos','historial']; }
}
