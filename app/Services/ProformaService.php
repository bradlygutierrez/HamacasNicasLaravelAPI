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
            $values = $this->pricing->calculatePayload($data, $user);
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
            $proforma->update($values['values'] + ['numero' => 'PRO-' . now()->format('Y') . '-' . str_pad((string) $proforma->id, 6, '0', STR_PAD_LEFT), 'estado' => 'emitida', 'emitida_at' => now()]);
            $this->replaceSnapshots($proforma, $values);
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
        $client = !empty($data['cliente_id']) ? Cliente::findOrFail($data['cliente_id']) : null;
        return ['cliente_id' => $client?->id, 'vendedor_id' => $seller->id, 'estado' => 'borrador', 'nombre_cliente' => $client?->nombre ?? $data['nombre_cliente'], 'ruc' => $data['ruc'] ?? $client?->ruc, 'direccion' => $data['direccion'] ?? $client?->direccion, 'telefono' => $data['telefono'] ?? $client?->telefono, 'correo' => $data['correo'] ?? $client?->correo, 'fecha' => $data['fecha'] ?? now()->toDateString(), 'valida_hasta' => $data['valida_hasta'] ?? null, 'observaciones' => $data['observaciones'] ?? null, 'aplica_iva' => (bool) ($data['aplica_iva'] ?? false), 'aplica_ir' => (bool) ($data['aplica_ir'] ?? false)];
    }

    private function replaceAggregate(Proforma $proforma, array $calculated): void
    {
        $proforma->detalles()->delete(); $proforma->servicios()->delete(); $proforma->materialesSnapshot()->delete(); $proforma->manoObraSnapshot()->delete();
        foreach ($calculated['details'] as $detail) { $line = $proforma->detalles()->create(['hamaca_id' => $detail['hamaca']->id, 'hamaca_variante_id' => $detail['input']['hamaca_variante_id'] ?? null, 'receta_hamaca_id' => $detail['recipe']->id, 'receta_version_snapshot' => $detail['recipe']->version, 'hamaca_nombre_snapshot' => $detail['hamaca']->nombre, 'hamaca_descripcion_snapshot' => $detail['hamaca']->descripcion, 'cantidad' => $detail['cantidad'], 'precio_unitario' => $detail['precio_unitario'], 'descuento' => $detail['descuento'], 'subtotal' => $detail['subtotal'], 'costo_unitario_estimado' => $detail['costo_unitario'], 'costo_total_estimado' => $detail['costo_total']]); foreach ($detail['services'] as $service) $line->servicios()->create($this->serviceAttributes($service)); }
        foreach ($calculated['services'] as $service) $proforma->servicios()->create($this->serviceAttributes($service)); $this->replaceSnapshots($proforma, $calculated);
    }

    private function replaceSnapshots(Proforma $proforma, array $calculated): void
    {
        $proforma->materialesSnapshot()->delete(); $proforma->manoObraSnapshot()->delete(); foreach ($calculated['material_snapshots'] as $snapshot) $proforma->materialesSnapshot()->create($snapshot); foreach ($calculated['labor_snapshots'] as $snapshot) $proforma->manoObraSnapshot()->create($snapshot);
    }

    private function serviceAttributes(array $service): array { $model = $service['service']; $input = $service['input']; return ['servicio_adicional_id' => $model->id, 'servicio_nombre_snapshot' => $model->nombre, 'alcance_snapshot' => $model->alcance, 'metodo_calculo_snapshot' => $model->metodo_calculo, 'unidad_snapshot' => $model->unidad, 'detalle' => $input['detalle'] ?? null, 'cantidad' => $service['cantidad'], 'precio_unitario' => $service['precio_unitario'], 'descuento' => $service['descuento'], 'subtotal' => $service['subtotal'], 'costo_base_unitario_snapshot' => $input['costo_base_unitario_override'] ?? $model->costo_actual, 'costo_unitario_estimado' => $service['costo_unitario'], 'costo_total_estimado' => $service['costo_total']]; }
    private function assertOwner(Proforma $proforma, Usuario $user): void { if ($user->rol === 'vendedor' && $proforma->vendedor_id !== $user->id) abort(403, 'No tenés acceso a esta proforma.'); }
    private function relations(): array { return ['cliente', 'vendedor', 'detalles.servicios', 'servicios', 'materialesSnapshot', 'manoObraSnapshot']; }
}
