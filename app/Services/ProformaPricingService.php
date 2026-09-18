<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Hamaca;
use App\Models\Proforma;
use App\Models\ServicioAdicional;
use App\Models\Usuario;

class ProformaPricingService
{
    public function __construct(private readonly CostoProduccionService $costoService) {}

    public function calculatePayload(array $payload, Usuario $user): array
    {
        $grossProducts = 0.0; $grossServices = 0.0; $lineDiscounts = 0.0; $details = []; $services = []; $materials = []; $labor = [];
        foreach ($payload['detalles'] ?? [] as $detailIndex => $input) {
            $hamaca = Hamaca::with(['recetaActiva.detallesMateriales.material', 'recetaActiva.detallesManoObra.proceso'])->findOrFail($input['hamaca_id']);
            if (!empty($input['hamaca_variante_id']) && !$hamaca->variantes()->where('state', true)->find($input['hamaca_variante_id'])) throw new BusinessRuleException('La variante no pertenece al modelo o está inactiva.', [], 422);
            $recipe = $hamaca->recetaActiva; if (!$recipe) throw new BusinessRuleException("El modelo {$hamaca->nombre} no tiene una fórmula activa.", [], 422);
            $quantity = (int) $input['cantidad']; $price = (float) ($input['precio_unitario'] ?? $hamaca->precio); $discount = (float) ($input['descuento'] ?? 0); $gross = $this->money($quantity * $price); $this->assertDiscount($discount, $gross, 'El descuento de línea no puede superar el importe bruto.');
            $grossProducts += $gross; $lineDiscounts += $discount; $recipeCost = $this->costoService->calcularReceta($recipe); $recipeUnitCost = (float) $recipeCost['resumen']['costo_produccion'];
            $line = ['input' => $input, 'hamaca' => $hamaca, 'recipe' => $recipe, 'cantidad' => $quantity, 'precio_unitario' => $price, 'descuento' => $this->money($discount), 'subtotal' => $this->money($gross - $discount), 'costo_unitario' => $this->money($recipeUnitCost), 'costo_total' => $this->money($recipeUnitCost * $quantity), 'services' => []];
            foreach ($recipe->detallesMateriales as $component) $materials[] = $this->materialSnapshot($component->material, (float) $component->cantidad, $quantity, $component->porcentaje_merma, 'receta', $detailIndex);
            foreach ($recipe->detallesManoObra as $component) $labor[] = $this->laborSnapshot($component->proceso, (float) $component->costo_unitario, $quantity, 'receta', $detailIndex, $component->orden);
            foreach ($input['servicios'] ?? [] as $serviceIndex => $serviceInput) {
                $service = ServicioAdicional::with(['materiales.material', 'manoObra.proceso'])->where('state', true)->findOrFail($serviceInput['servicio_adicional_id']); if ($service->alcance !== 'producto') throw new BusinessRuleException('El servicio seleccionado no aplica a productos.', [], 422);
                $serviceQuantity = (float) $serviceInput['cantidad']; if ($service->metodo_calculo === 'por_producto' && $serviceQuantity > $quantity) throw new BusinessRuleException('La cantidad del servicio por producto supera la cantidad de hamacas.', [], 422);
                $servicePrice = (float) ($serviceInput['precio_unitario'] ?? $service->precio_venta_actual); $serviceDiscount = (float) ($serviceInput['descuento'] ?? 0); $serviceGross = $this->money($serviceQuantity * $servicePrice); $this->assertDiscount($serviceDiscount, $serviceGross, 'El descuento del servicio no puede superar su importe.'); $serviceCost = $this->costoService->calcularServicio($service); $baseCost = (float) $service->costo_actual; $costUnit = $this->money($baseCost + (float) $serviceCost['resumen']['costo_materiales'] + (float) $serviceCost['resumen']['costo_mano_obra']);
                $grossServices += $serviceGross; $lineDiscounts += $serviceDiscount; $line['services'][] = ['service' => $service, 'input' => $serviceInput, 'cantidad' => $serviceQuantity, 'precio_unitario' => $servicePrice, 'descuento' => $this->money($serviceDiscount), 'subtotal' => $this->money($serviceGross - $serviceDiscount), 'costo_base' => $baseCost, 'costo_unitario' => $costUnit, 'costo_total' => $this->money($costUnit * $serviceQuantity)];
                foreach ($service->materiales as $component) $materials[] = $this->materialSnapshot($component->material, (float) $component->cantidad, $serviceQuantity, $component->porcentaje_merma, 'servicio_producto', $detailIndex . ':' . $serviceIndex);
                foreach ($service->manoObra as $component) $labor[] = $this->laborSnapshot($component->proceso, (float) $component->costo_unitario, $serviceQuantity, 'servicio_producto', $detailIndex . ':' . $serviceIndex, $component->orden);
            }
            $details[] = $line;
        }
        foreach ($payload['servicios_pedido'] ?? [] as $index => $input) {
            $service = ServicioAdicional::with(['materiales.material', 'manoObra.proceso'])->where('state', true)->findOrFail($input['servicio_adicional_id']); if ($service->alcance !== 'pedido') throw new BusinessRuleException('El servicio seleccionado no aplica al pedido.', [], 422);
            $quantity = (float) $input['cantidad']; $price = (float) ($input['precio_unitario'] ?? $service->precio_venta_actual); $discount = (float) ($input['descuento'] ?? 0); $gross = $this->money($quantity * $price); $this->assertDiscount($discount, $gross, 'El descuento del servicio no puede superar su importe.'); $override = $user->rol === 'admin' && array_key_exists('costo_base_unitario_override', $input) ? $input['costo_base_unitario_override'] : null; $baseCost = $override === null ? (float) $service->costo_actual : (float) $override; $serviceCost = $this->costoService->calcularServicio($service); $costUnit = $this->money($baseCost + (float) $serviceCost['resumen']['costo_materiales'] + (float) $serviceCost['resumen']['costo_mano_obra']);
            $grossServices += $gross; $lineDiscounts += $discount; $services[] = ['service' => $service, 'input' => $input, 'cantidad' => $quantity, 'precio_unitario' => $price, 'descuento' => $this->money($discount), 'subtotal' => $this->money($gross - $discount), 'costo_base' => $baseCost, 'costo_base_override' => $override, 'costo_unitario' => $costUnit, 'costo_total' => $this->money($costUnit * $quantity)];
            foreach ($service->materiales as $component) $materials[] = $this->materialSnapshot($component->material, (float) $component->cantidad, $quantity, $component->porcentaje_merma, 'servicio_pedido', $index);
            foreach ($service->manoObra as $component) $labor[] = $this->laborSnapshot($component->proceso, (float) $component->costo_unitario, $quantity, 'servicio_pedido', $index, $component->orden);
        }
        $grossSubtotal = $this->money($grossProducts + $grossServices); $globalDiscount = (float) ($payload['descuento_global'] ?? $payload['descuento'] ?? 0); $discountTotal = $this->money($lineDiscounts + $globalDiscount); if ($discountTotal > $grossSubtotal) throw new BusinessRuleException('El descuento total no puede superar el subtotal bruto.', [], 422); $base = $this->money($grossSubtotal - $discountTotal);
        $ivaRate = $user->rol === 'vendedor' ? (float) config('proformas.iva_rate') : (float) ($payload['tasa_iva'] ?? config('proformas.iva_rate')); $irRate = $user->rol === 'vendedor' ? (float) config('proformas.ir_rate') : (float) ($payload['tasa_ir'] ?? config('proformas.ir_rate')); $commissionRate = $user->rol === 'vendedor' ? (float) config('proformas.commission_rate') : (float) ($payload['tasa_comision_vendedor'] ?? config('proformas.commission_rate')); $iva = !empty($payload['aplica_iva']) ? $this->money($base * $ivaRate / 100) : 0.0; $ir = !empty($payload['aplica_ir']) ? $this->money($base * $irRate / 100) : 0.0; $commission = $this->money($base * $commissionRate / 100);
        $materialCost = $this->money(array_sum(array_map(fn ($item) => (float) $item['costo_consumo_total'], $materials))); $laborCost = $this->money(array_sum(array_map(fn ($item) => (float) $item['costo_total'], $labor))); $serviceRows = $services; foreach ($details as $detail) $serviceRows = array_merge($serviceRows, $detail['services']); $serviceBaseCost = $this->money(array_sum(array_map(fn ($item) => (float) $item['costo_base'] * (float) $item['cantidad'], $serviceRows))); $purchase = $this->materialPurchaseBreakdown($materials); $totalCost = $this->money($materialCost + $laborCost + $serviceBaseCost); $total = $this->money($base + $iva - $ir);
        $values = ['subtotal_productos' => $this->money($grossProducts), 'subtotal_servicios' => $this->money($grossServices), 'subtotal_bruto' => $grossSubtotal, 'descuento_global' => $this->money($globalDiscount), 'descuento_lineas' => $this->money($lineDiscounts), 'descuento_total' => $discountTotal, 'descuento' => $discountTotal, 'base_neta' => $base, 'tasa_iva' => $ivaRate, 'monto_iva' => $iva, 'tasa_ir' => $irRate, 'monto_ir' => $ir, 'tasa_comision_vendedor' => $commissionRate, 'monto_comision_vendedor' => $commission, 'costo_materiales_estimado' => $materialCost, 'costo_mano_de_obra_estimado' => $laborCost, 'costo_servicios_base_estimado' => $serviceBaseCost, 'costo_total_estimado' => $totalCost, 'costo_compra_estimado' => $purchase['total'], 'utilidad_estimada' => $this->money($base - $totalCost - $commission), 'total' => $total];
        return ['details' => $details, 'services' => $services, 'material_snapshots' => $materials, 'labor_snapshots' => $labor, 'materiales_agrupados' => $purchase['items'], 'values' => $values];
    }

    public function calculateProforma(Proforma $proforma, Usuario $user): array
    {
        $proforma->load(['detalles.servicios', 'servicios']); $payload = ['descuento_global' => $proforma->descuento_global ?? $proforma->descuento ?? 0, 'aplica_iva' => $proforma->aplica_iva, 'tasa_iva' => $proforma->tasa_iva, 'aplica_ir' => $proforma->aplica_ir, 'tasa_ir' => $proforma->tasa_ir, 'tasa_comision_vendedor' => $proforma->tasa_comision_vendedor, 'detalles' => [], 'servicios_pedido' => []];
        foreach ($proforma->detalles as $detail) $payload['detalles'][] = ['hamaca_id' => $detail->hamaca_id, 'hamaca_variante_id' => $detail->hamaca_variante_id, 'cantidad' => $detail->cantidad, 'precio_unitario' => $detail->precio_unitario, 'descuento' => $detail->descuento, 'servicios' => $detail->servicios->map(fn ($s) => ['servicio_adicional_id' => $s->servicio_adicional_id, 'cantidad' => $s->cantidad, 'detalle' => $s->detalle, 'precio_unitario' => $s->precio_unitario, 'descuento' => $s->descuento, 'costo_base_unitario_override' => $s->costo_base_unitario_override])->all()]; foreach ($proforma->servicios as $service) $payload['servicios_pedido'][] = ['servicio_adicional_id' => $service->servicio_adicional_id, 'cantidad' => $service->cantidad, 'detalle' => $service->detalle, 'precio_unitario' => $service->precio_unitario, 'descuento' => $service->descuento, 'costo_base_unitario_override' => $service->costo_base_unitario_override];
        return $this->calculatePayload($payload, $user);
    }

    public function materialPurchaseBreakdown(array $snapshots): array
    {
        $groups = []; foreach ($snapshots as $snapshot) { $key = (string) $snapshot['material_id']; if (!isset($groups[$key])) $groups[$key] = ['material_id' => $snapshot['material_id'], 'nombre' => $snapshot['material_nombre_snapshot'], 'unidad_consumo' => $snapshot['unidad_consumo_snapshot'], 'cantidad_requerida' => 0.0, 'unidad_compra' => $snapshot['unidad_compra_snapshot'], 'contenido_por_compra' => (float) $snapshot['contenido_por_compra_snapshot'], 'precio_compra' => (float) $snapshot['precio_compra_snapshot'], 'costo_consumo' => 0.0]; $groups[$key]['cantidad_requerida'] += (float) $snapshot['cantidad_total_con_merma']; $groups[$key]['costo_consumo'] += (float) $snapshot['costo_consumo_total']; }
        $total = 0.0; foreach ($groups as &$group) { $group['cantidad_compra'] = (int) ceil($group['cantidad_requerida'] / $group['contenido_por_compra']); $group['costo_compra'] = $this->money($group['cantidad_compra'] * $group['precio_compra']); $group['cantidad_requerida'] = number_format($group['cantidad_requerida'], 4, '.', ''); $group['costo_consumo'] = $this->money($group['costo_consumo']); $total += $group['costo_compra']; } unset($group); return ['items' => array_values($groups), 'total' => $this->money($total)];
    }

    private function assertDiscount(float $discount, float $gross, string $message): void { if ($discount < 0 || $discount > $gross) throw new BusinessRuleException($message, [], 422); }
    private function money(float $value): float { return (float) $this->moneyAdd((string) $value, '0'); }
    private function moneyAdd(string $a, string $b): string { return $this->bcRound(bcadd($a, $b, 6), 2); }
    private function moneySub(string $a, string $b): string { return $this->bcRound(bcsub($a, $b, 6), 2); }
    private function moneyMul(string $a, string $b): string { return $this->bcRound(bcmul($a, $b, 6), 2); }
    private function moneyDiv(string $a, string $b): string { return $this->bcRound(bcdiv($a, $b, 8), 2); }
    private function moneyPercent(string $amount, string $rate): string { return $this->moneyDiv($this->moneyMul($amount, $rate), '100'); }
    private function bcRound(string $value, int $scale): string { $negative = str_starts_with($value, '-'); $absolute = ltrim($value, '-'); $rounded = bcadd($absolute, '0.005', $scale); return ($negative ? '-' : '') . $rounded; }
    private function materialSnapshot($material, float $base, float $factor, $override, string $origin, $originId): array { $waste = $override === null ? (float) $material->porcentaje_merma : (float) $override; $content = (float) $material->contenido_por_compra; $unit = (float) $material->precio_actual / $content; $totalQty = $base * (1 + $waste / 100) * $factor; return ['origen_tipo' => $origin, 'origen_id' => $originId, 'material_id' => $material->id, 'material_nombre_snapshot' => $material->nombre, 'unidad_consumo_snapshot' => $material->unidad_consumo, 'unidad_compra_snapshot' => $material->unidad_compra, 'cantidad_base_unitaria' => $base, 'factor_cantidad' => $factor, 'porcentaje_merma' => $waste, 'cantidad_total_con_merma' => $totalQty, 'contenido_por_compra_snapshot' => $content, 'precio_compra_snapshot' => $material->precio_actual, 'costo_unidad_consumo_snapshot' => $unit, 'costo_consumo_total' => $this->money($totalQty * $unit)]; }
    private function laborSnapshot($process, float $cost, float $factor, string $origin, $originId, $order): array { return ['origen_tipo' => $origin, 'origen_id' => $originId, 'proceso_produccion_id' => $process?->id, 'proceso_nombre_snapshot' => $process?->nombre ?? 'Proceso', 'costo_unitario_snapshot' => $cost, 'factor_cantidad' => $factor, 'costo_total' => $this->money($cost * $factor), 'orden' => $order]; }
}
