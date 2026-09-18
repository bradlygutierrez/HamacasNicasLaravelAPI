<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Cliente;
use App\Models\Proforma;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class ProformaService
{
    public function __construct(private readonly ProformaPricingService $pricing)
    {
    }

    public function createDraft(array $data, Usuario $user): Proforma
    {
        return DB::transaction(function () use ($data, $user): Proforma {
            $values = $this->pricing->calculatePayload($data, $user);
            $proforma = Proforma::create($this->baseAttributes($data, $user) + $values['values']);
            $this->replaceAggregate($proforma, $values);
            return $proforma->fresh($this->relations());
        });
    }

    public function updateDraft(Proforma $proforma, array $data, Usuario $user): Proforma
    {
        return DB::transaction(function () use ($proforma, $data, $user): Proforma {
            $proforma = Proforma::query()->lockForUpdate()->findOrFail($proforma->id);
            $this->assertOwner($proforma, $user);
            if ($proforma->estado !== 'borrador') throw new BusinessRuleException('Una proforma emitida no se puede editar.');
            $authorizedSettings = $user->rol === 'vendedor' ? $this->persistedAuthorizedSettings($proforma) : null;
            $values = $this->pricing->calculatePayload($data, $user, $authorizedSettings);
            $proforma->update($this->baseAttributes($data, $user) + $values['values']);
            $this->replaceAggregate($proforma, $values);
            return $proforma->fresh($this->relations());
        });
    }

    public function emit(Proforma $proforma, Usuario $user): Proforma
    {
        return DB::transaction(function () use ($proforma, $user): Proforma {
            $proforma = Proforma::query()->lockForUpdate()->findOrFail($proforma->id);
            $this->assertOwner($proforma, $user);
            if ($proforma->estado !== 'borrador') return $proforma->fresh($this->relations());
            $values = $this->pricing->calculateProforma($proforma, $user);
            $this->replaceAggregate($proforma, $values);
            $proforma->update($values['values'] + ['numero' => 'PRO-' . now()->format('Y') . '-' . str_pad((string) $proforma->id, 6, '0', STR_PAD_LEFT), 'estado' => 'emitida', 'emitida_at' => now()]);
            return $proforma->fresh($this->relations());
        });
    }

    public function changeStatus(Proforma $proforma, string $state, Usuario $user): Proforma
    {
        return DB::transaction(function () use ($proforma, $state, $user): Proforma {
            $proforma = Proforma::query()->lockForUpdate()->findOrFail($proforma->id); $this->assertOwner($proforma, $user);
            $allowed = ['emitida' => ['enviada', 'aceptada', 'rechazada', 'vencida'], 'enviada' => ['aceptada', 'rechazada', 'vencida']];
            if (!in_array($state, $allowed[$proforma->estado] ?? [], true)) throw new BusinessRuleException('La transición de estado no es válida.');
            $proforma->update(['estado' => $state]); return $proforma->fresh($this->relations());
        });
    }

    private function baseAttributes(array $data, Usuario $user): array
    {
        $sellerId = $user->rol === 'vendedor' ? $user->id : ($data['vendedor_id'] ?? $user->id); $seller = Usuario::where('state', true)->whereIn('rol', ['admin', 'vendedor'])->find($sellerId);
        if (!$seller) throw new BusinessRuleException('El vendedor seleccionado no está activo.', [], 422);
        $client = !empty($data['cliente_id']) ? Cliente::where('state', true)->findOrFail($data['cliente_id']) : null;
        return ['cliente_id' => $client?->id, 'vendedor_id' => $seller->id, 'estado' => 'borrador', 'nombre_cliente' => array_key_exists('nombre_cliente', $data) ? $data['nombre_cliente'] : $client?->nombre, 'ruc' => array_key_exists('ruc', $data) ? $data['ruc'] : $client?->ruc, 'direccion' => array_key_exists('direccion', $data) ? $data['direccion'] : $client?->direccion, 'telefono' => array_key_exists('telefono', $data) ? $data['telefono'] : $client?->telefono, 'correo' => array_key_exists('correo', $data) ? $data['correo'] : $client?->correo, 'fecha' => $data['fecha'] ?? now()->toDateString(), 'valida_hasta' => $data['valida_hasta'] ?? null, 'observaciones' => $data['observaciones'] ?? null, 'aplica_iva' => (bool) ($data['aplica_iva'] ?? false), 'aplica_ir' => (bool) ($data['aplica_ir'] ?? false)];
    }

    private function replaceAggregate(Proforma $proforma, array $calculated): void
    {
        $proforma->detalles()->delete(); $proforma->servicios()->delete(); $proforma->materialesSnapshot()->delete(); $proforma->manoObraSnapshot()->delete(); $detailIds = []; $serviceIds = [];
        foreach ($calculated['details'] as $detailIndex => $detail) { $line = $proforma->detalles()->create(['hamaca_id' => $detail['hamaca']->id, 'hamaca_variante_id' => $detail['input']['hamaca_variante_id'] ?? null, 'receta_hamaca_id' => $detail['recipe']->id, 'receta_version_snapshot' => $detail['recipe']->version, 'hamaca_nombre_snapshot' => $detail['hamaca']->nombre, 'hamaca_descripcion_snapshot' => $detail['hamaca']->descripcion, 'cantidad' => $detail['cantidad'], 'precio_unitario' => $detail['precio_unitario'], 'descuento' => $detail['descuento'], 'subtotal' => $detail['subtotal'], 'costo_unitario_estimado' => $detail['costo_unitario'], 'costo_total_estimado' => $detail['costo_total']]); $detailIds[$detailIndex] = $line->id; foreach ($detail['services'] as $serviceIndex => $service) { $row = $line->servicios()->create($this->serviceAttributes($service)); $serviceIds[$detailIndex . ':' . $serviceIndex] = $row->id; } }
        foreach ($calculated['services'] as $serviceIndex => $service) { $row = $proforma->servicios()->create($this->serviceAttributes($service)); $serviceIds['pedido:' . $serviceIndex] = $row->id; } $this->replaceSnapshots($proforma, $calculated, $detailIds, $serviceIds);
    }

    private function replaceSnapshots(Proforma $proforma, array $calculated, array $detailIds = [], array $serviceIds = []): void
    {
        $proforma->materialesSnapshot()->delete(); $proforma->manoObraSnapshot()->delete(); foreach ($calculated['material_snapshots'] as $snapshot) { $snapshot['origen_id'] = $this->sourceId($snapshot, $detailIds, $serviceIds); unset($snapshot['origen_ref']); $proforma->materialesSnapshot()->create($snapshot); } foreach ($calculated['labor_snapshots'] as $snapshot) { $snapshot['origen_id'] = $this->sourceId($snapshot, $detailIds, $serviceIds); unset($snapshot['origen_ref']); $proforma->manoObraSnapshot()->create($snapshot); }
    }

    private function sourceId(array $snapshot, array $detailIds, array $serviceIds): int { if ($snapshot['origen_tipo'] === 'receta') return $detailIds[(int) $snapshot['origen_id']] ?? 0; if ($snapshot['origen_tipo'] === 'servicio_pedido') return $serviceIds['pedido:' . $snapshot['origen_id']] ?? 0; return $serviceIds[(string) $snapshot['origen_id']] ?? 0; }

    private function serviceAttributes(array $service): array { $model = $service['service']; $input = $service['input']; return ['servicio_adicional_id' => $model->id, 'servicio_nombre_snapshot' => $model->nombre, 'alcance_snapshot' => $model->alcance, 'metodo_calculo_snapshot' => $model->metodo_calculo, 'unidad_snapshot' => $model->unidad, 'detalle' => $input['detalle'] ?? null, 'cantidad' => $service['cantidad'], 'precio_unitario' => $service['precio_unitario'], 'descuento' => $service['descuento'], 'subtotal' => $service['subtotal'], 'costo_base_unitario_override' => $service['costo_base_override'] ?? null, 'costo_base_unitario_snapshot' => $service['costo_base'], 'costo_unitario_estimado' => $service['costo_unitario'], 'costo_total_estimado' => $service['costo_total']]; }
    private function persistedAuthorizedSettings(Proforma $proforma): array { $proforma->loadMissing('servicios'); return ['tasa_iva' => $proforma->tasa_iva, 'tasa_ir' => $proforma->tasa_ir, 'tasa_comision_vendedor' => $proforma->tasa_comision_vendedor, 'service_overrides' => $proforma->servicios->mapWithKeys(fn ($service) => [(string) $service->servicio_adicional_id => $service->costo_base_unitario_override])->all()]; }
    private function assertOwner(Proforma $proforma, Usuario $user): void { if ($user->rol === 'vendedor' && $proforma->vendedor_id !== $user->id) abort(403, 'No tenés acceso a esta proforma.'); }
    private function relations(): array { return ['cliente', 'vendedor', 'detalles.servicios', 'servicios', 'materialesSnapshot', 'manoObraSnapshot']; }
}
