<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeProformaStatusRequest;
use App\Http\Requests\ProformaRequest;
use App\Http\Resources\V1\ProformaResource;
use App\Models\Proforma;
use App\Services\ProformaPricingService;
use App\Services\ProformaService;
use Illuminate\Http\Request;

class ProformaController extends Controller
{
    public function __construct(private readonly ProformaService $service, private readonly ProformaPricingService $pricing)
    {
    }

    public function index(Request $request)
    {
        $query = Proforma::with(['vendedor'])->when($request->user()->rol === 'vendedor', fn ($q) => $q->where('vendedor_id', $request->user()->id))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('numero', 'like', '%' . $request->string('search') . '%')->orWhere('nombre_cliente', 'like', '%' . $request->string('search') . '%')->orWhere('ruc', 'like', '%' . $request->string('search') . '%')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('vendedor_id') && $request->user()->rol !== 'vendedor', fn ($q) => $q->where('vendedor_id', $request->integer('vendedor_id')))
            ->when($request->filled('cliente_id'), fn ($q) => $q->where('cliente_id', $request->integer('cliente_id')))
            ->latest();
        return ProformaResource::collection($query->paginate(min(max($request->integer('per_page', 15), 1), 100)));
    }

    public function store(ProformaRequest $request)
    {
        return (new ProformaResource($this->service->createDraft($request->validated(), $request->user())))->response()->setStatusCode(201);
    }

    public function show(Request $request, Proforma $proforma): ProformaResource
    {
        $this->assertOwner($request, $proforma); return new ProformaResource($proforma->load(['cliente', 'vendedor', 'detalles.servicios', 'servicios', 'materialesSnapshot', 'manoObraSnapshot']));
    }

    public function update(ProformaRequest $request, Proforma $proforma): ProformaResource { return new ProformaResource($this->service->updateDraft($proforma, $request->validated(), $request->user())); }
    public function calculate(ProformaRequest $request) { $result = $this->pricing->calculatePayload($request->validated(), $request->user()); return response()->json(['data' => $this->publicCalculation($result, $request->user()->rol)]); }
    public function emit(Request $request, Proforma $proforma): ProformaResource { return new ProformaResource($this->service->emit($proforma, $request->user())); }
    public function status(ChangeProformaStatusRequest $request, Proforma $proforma): ProformaResource { return new ProformaResource($this->service->changeStatus($proforma, $request->validated('estado'), $request->user())); }

    private function assertOwner(Request $request, Proforma $proforma): void { if ($request->user()->rol === 'vendedor' && $proforma->vendedor_id !== $request->user()->id) abort(403, 'No tenés acceso a esta proforma.'); }
    private function publicCalculation(array $result, string $role): array { $internal = in_array($role, ['admin', 'socio'], true); $data = ['values' => $result['values'], 'detalles' => array_map(fn ($detail) => ['nombre' => $detail['hamaca']->nombre, 'cantidad' => $detail['cantidad'], 'precio_unitario' => $detail['precio_unitario'], 'descuento' => $detail['descuento'], 'subtotal' => $detail['subtotal'], 'servicios' => array_map(fn ($service) => ['nombre' => $service['service']->nombre, 'cantidad' => $service['cantidad'], 'precio_unitario' => $service['precio_unitario'], 'descuento' => $service['descuento'], 'subtotal' => $service['subtotal']], $detail['services'])], $result['details']), 'servicios_pedido' => array_map(fn ($service) => ['nombre' => $service['service']->nombre, 'cantidad' => $service['cantidad'], 'precio_unitario' => $service['precio_unitario'], 'descuento' => $service['descuento'], 'subtotal' => $service['subtotal']], $result['services'])]; if ($internal) $data['analisis_interno'] = ['materiales' => $result['material_snapshots'], 'mano_obra' => $result['labor_snapshots'], 'costo_total_estimado' => $result['values']['costo_total_estimado'], 'costo_compra_estimado' => $result['values']['costo_compra_estimado'], 'monto_comision_vendedor' => $result['values']['monto_comision_vendedor'], 'utilidad_estimada' => $result['values']['utilidad_estimada']]; return $data; }
}
