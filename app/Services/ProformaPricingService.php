<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Hamaca;
use App\Models\Proforma;
use App\Models\ServicioAdicional;
use App\Models\Usuario;
use App\Support\DecimalMoney;

class ProformaPricingService
{
    public function __construct(private readonly CostoProduccionService $costoService) {}

    public function calculatePayload(array $payload, Usuario $user, ?array $authorizedSettings = null): array
    {
        $grossProducts = '0.00'; $grossServices = '0.00'; $lineDiscounts = '0.00';
        $details = []; $services = []; $materials = []; $labor = [];
        foreach ($payload['detalles'] ?? [] as $detailIndex => $input) {
            $hamaca = Hamaca::with(['recetaActiva.detallesMateriales.material', 'recetaActiva.detallesManoObra.proceso'])->findOrFail($input['hamaca_id']);
            if (!empty($input['hamaca_variante_id']) && !$hamaca->variantes()->where('state', true)->find($input['hamaca_variante_id'])) throw new BusinessRuleException('La variante no pertenece al modelo o está inactiva.', [], 422);
            $recipe = $hamaca->recetaActiva;
            if (!$recipe) throw new BusinessRuleException("El modelo {$hamaca->nombre} no tiene una fórmula activa.", [], 422);
            $quantity = (int) $input['cantidad']; $price = (string) ($input['precio_unitario'] ?? $hamaca->precio); $discount = (string) ($input['descuento'] ?? '0');
            $gross = DecimalMoney::mul((string) $quantity, $price); $this->assertDiscount($discount, $gross, 'El descuento de línea no puede superar el importe bruto.');
            $grossProducts = DecimalMoney::add($grossProducts, $gross); $lineDiscounts = DecimalMoney::add($lineDiscounts, $discount);
            $recipeCost = $this->costoService->calcularReceta($recipe); $recipeUnitCost = (string) $recipeCost['resumen']['costo_produccion'];
            $line = ['input' => $input, 'hamaca' => $hamaca, 'recipe' => $recipe, 'cantidad' => $quantity, 'precio_unitario' => $price, 'descuento' => DecimalMoney::add($discount, '0'), 'subtotal' => DecimalMoney::sub($gross, $discount), 'costo_unitario' => DecimalMoney::add($recipeUnitCost, '0'), 'costo_total' => DecimalMoney::mul($recipeUnitCost, (string) $quantity), 'services' => []];
            foreach ($recipe->detallesMateriales as $component) $materials[] = $this->materialSnapshot($component->material, (string) $component->cantidad, (string) $quantity, $component->porcentaje_merma, 'receta', $detailIndex);
            foreach ($recipe->detallesManoObra as $component) $labor[] = $this->laborSnapshot($component->proceso, (string) $component->costo_unitario, (string) $quantity, 'receta', $detailIndex, $component->orden);
            foreach ($input['servicios'] ?? [] as $serviceIndex => $serviceInput) {
                $service = ServicioAdicional::with(['materiales.material', 'manoObra.proceso'])->where('state', true)->findOrFail($serviceInput['servicio_adicional_id']);
                if ($service->alcance !== 'producto') throw new BusinessRuleException('El servicio seleccionado no aplica a productos.', [], 422);
                $serviceQuantity = (string) $serviceInput['cantidad'];
                if ($service->metodo_calculo === 'por_producto' && DecimalMoney::compare($serviceQuantity, (string) $quantity) > 0) throw new BusinessRuleException('La cantidad del servicio por producto supera la cantidad de hamacas.', [], 422);
                $servicePrice = (string) ($serviceInput['precio_unitario'] ?? $service->precio_venta_actual); $serviceDiscount = (string) ($serviceInput['descuento'] ?? '0'); $serviceGross = DecimalMoney::mul($serviceQuantity, $servicePrice); $this->assertDiscount($serviceDiscount, $serviceGross, 'El descuento del servicio no puede superar su importe.');
                $serviceCost = $this->costoService->calcularServicio($service); $baseCost = (string) $service->costo_actual; $costUnit = DecimalMoney::add($baseCost, DecimalMoney::add((string) $serviceCost['resumen']['costo_materiales'], (string) $serviceCost['resumen']['costo_mano_obra']));
                $grossServices = DecimalMoney::add($grossServices, $serviceGross); $lineDiscounts = DecimalMoney::add($lineDiscounts, $serviceDiscount);
                $line['services'][] = ['service' => $service, 'input' => $serviceInput, 'cantidad' => $serviceQuantity, 'precio_unitario' => $servicePrice, 'descuento' => DecimalMoney::add($serviceDiscount, '0'), 'subtotal' => DecimalMoney::sub($serviceGross, $serviceDiscount), 'costo_base' => $baseCost, 'costo_unitario' => $costUnit, 'costo_total' => DecimalMoney::mul($costUnit, $serviceQuantity)];
                foreach ($service->materiales as $component) $materials[] = $this->materialSnapshot($component->material, (string) $component->cantidad, $serviceQuantity, $component->porcentaje_merma, 'servicio_producto', $detailIndex . ':' . $serviceIndex);
                foreach ($service->manoObra as $component) $labor[] = $this->laborSnapshot($component->proceso, (string) $component->costo_unitario, $serviceQuantity, 'servicio_producto', $detailIndex . ':' . $serviceIndex, $component->orden);
            }
            $details[] = $line;
        }
        foreach ($payload['servicios_pedido'] ?? [] as $index => $input) {
            $service = ServicioAdicional::with(['materiales.material', 'manoObra.proceso'])->where('state', true)->findOrFail($input['servicio_adicional_id']);
            if ($service->alcance !== 'pedido') throw new BusinessRuleException('El servicio seleccionado no aplica al pedido.', [], 422);
            $quantity = (string) $input['cantidad']; $price = (string) ($input['precio_unitario'] ?? $service->precio_venta_actual); $discount = (string) ($input['descuento'] ?? '0'); $gross = DecimalMoney::mul($quantity, $price); $this->assertDiscount($discount, $gross, 'El descuento del servicio no puede superar su importe.');
            $serviceOverrideKey = (string) $service->id; $override = $authorizedSettings !== null && array_key_exists($serviceOverrideKey, $authorizedSettings['service_overrides'] ?? []) ? $authorizedSettings['service_overrides'][$serviceOverrideKey] : ($user->rol === 'admin' && array_key_exists('costo_base_unitario_override', $input) ? $input['costo_base_unitario_override'] : null); $baseCost = $override === null ? (string) $service->costo_actual : (string) $override; $serviceCost = $this->costoService->calcularServicio($service); $costUnit = DecimalMoney::add($baseCost, DecimalMoney::add((string) $serviceCost['resumen']['costo_materiales'], (string) $serviceCost['resumen']['costo_mano_obra']));
            $grossServices = DecimalMoney::add($grossServices, $gross); $lineDiscounts = DecimalMoney::add($lineDiscounts, $discount); $services[] = ['service' => $service, 'input' => $input, 'cantidad' => $quantity, 'precio_unitario' => $price, 'descuento' => DecimalMoney::add($discount, '0'), 'subtotal' => DecimalMoney::sub($gross, $discount), 'costo_base' => $baseCost, 'costo_base_override' => $override === null ? null : (string) $override, 'costo_unitario' => $costUnit, 'costo_total' => DecimalMoney::mul($costUnit, $quantity)];
            foreach ($service->materiales as $component) $materials[] = $this->materialSnapshot($component->material, (string) $component->cantidad, $quantity, $component->porcentaje_merma, 'servicio_pedido', $index);
            foreach ($service->manoObra as $component) $labor[] = $this->laborSnapshot($component->proceso, (string) $component->costo_unitario, $quantity, 'servicio_pedido', $index, $component->orden);
        }
        $grossSubtotal = DecimalMoney::add($grossProducts, $grossServices); $globalDiscount = (string) ($payload['descuento_global'] ?? $payload['descuento'] ?? '0'); $discountTotal = DecimalMoney::add($lineDiscounts, $globalDiscount); if (DecimalMoney::compare($discountTotal, $grossSubtotal) > 0) throw new BusinessRuleException('El descuento total no puede superar el subtotal bruto.', [], 422); $base = DecimalMoney::sub($grossSubtotal, $discountTotal);
        $ivaRate = array_key_exists('tasa_iva', $authorizedSettings ?? []) ? (string) $authorizedSettings['tasa_iva'] : ($user->rol === 'vendedor' ? (string) config('proformas.iva_rate') : (string) ($payload['tasa_iva'] ?? config('proformas.iva_rate'))); $irRate = array_key_exists('tasa_ir', $authorizedSettings ?? []) ? (string) $authorizedSettings['tasa_ir'] : ($user->rol === 'vendedor' ? (string) config('proformas.ir_rate') : (string) ($payload['tasa_ir'] ?? config('proformas.ir_rate'))); $commissionRate = array_key_exists('tasa_comision_vendedor', $authorizedSettings ?? []) ? (string) $authorizedSettings['tasa_comision_vendedor'] : ($user->rol === 'vendedor' ? (string) config('proformas.commission_rate') : (string) ($payload['tasa_comision_vendedor'] ?? config('proformas.commission_rate'))); $iva = !empty($payload['aplica_iva']) ? DecimalMoney::percent($base, $ivaRate) : '0.00'; $ir = !empty($payload['aplica_ir']) ? DecimalMoney::percent($base, $irRate) : '0.00'; $commission = DecimalMoney::percent($base, $commissionRate);
        $materialCost = $this->sum($materials, 'costo_consumo_total'); $laborCost = $this->sum($labor, 'costo_total'); $serviceRows = $services; foreach ($details as $detail) $serviceRows = array_merge($serviceRows, $detail['services']); $serviceBaseCost = '0.00'; foreach ($serviceRows as $row) $serviceBaseCost = DecimalMoney::add($serviceBaseCost, DecimalMoney::mul((string) $row['costo_base'], (string) $row['cantidad'])); $purchase = $this->materialPurchaseBreakdown($materials); $totalCost = DecimalMoney::add(DecimalMoney::add($materialCost, $laborCost), $serviceBaseCost); $total = DecimalMoney::sub(DecimalMoney::add($base, $iva), $ir);
        $values = ['subtotal_productos' => $grossProducts, 'subtotal_servicios' => $grossServices, 'subtotal_bruto' => $grossSubtotal, 'descuento_global' => DecimalMoney::add($globalDiscount, '0'), 'descuento_lineas' => $lineDiscounts, 'descuento_total' => $discountTotal, 'descuento' => $discountTotal, 'base_neta' => $base, 'tasa_iva' => $ivaRate, 'monto_iva' => $iva, 'tasa_ir' => $irRate, 'monto_ir' => $ir, 'tasa_comision_vendedor' => $commissionRate, 'monto_comision_vendedor' => $commission, 'costo_materiales_estimado' => $materialCost, 'costo_mano_de_obra_estimado' => $laborCost, 'costo_servicios_base_estimado' => $serviceBaseCost, 'costo_total_estimado' => $totalCost, 'costo_compra_estimado' => $purchase['total'], 'utilidad_estimada' => DecimalMoney::sub(DecimalMoney::sub($base, $totalCost), $commission), 'total' => $total];
        return ['details' => $details, 'services' => $services, 'material_snapshots' => $materials, 'labor_snapshots' => $labor, 'materiales_agrupados' => $purchase['items'], 'values' => $values];
    }

    public function calculateProforma(Proforma $proforma, Usuario $user): array
    {
        $proforma->load(['detalles.servicios', 'servicios']); $payload = ['descuento_global' => $proforma->descuento_global ?? $proforma->descuento ?? '0', 'aplica_iva' => $proforma->aplica_iva, 'tasa_iva' => $proforma->tasa_iva, 'aplica_ir' => $proforma->aplica_ir, 'tasa_ir' => $proforma->tasa_ir, 'tasa_comision_vendedor' => $proforma->tasa_comision_vendedor, 'detalles' => [], 'servicios_pedido' => []];
        foreach ($proforma->detalles as $detail) $payload['detalles'][] = ['hamaca_id' => $detail->hamaca_id, 'hamaca_variante_id' => $detail->hamaca_variante_id, 'cantidad' => $detail->cantidad, 'precio_unitario' => $detail->precio_unitario, 'descuento' => $detail->descuento, 'servicios' => $detail->servicios->map(fn ($s) => ['servicio_adicional_id' => $s->servicio_adicional_id, 'cantidad' => $s->cantidad, 'detalle' => $s->detalle, 'precio_unitario' => $s->precio_unitario, 'descuento' => $s->descuento, 'costo_base_unitario_override' => $s->costo_base_unitario_override])->all()];
        foreach ($proforma->servicios as $service) $payload['servicios_pedido'][] = ['servicio_adicional_id' => $service->servicio_adicional_id, 'cantidad' => $service->cantidad, 'detalle' => $service->detalle, 'precio_unitario' => $service->precio_unitario, 'descuento' => $service->descuento, 'costo_base_unitario_override' => $service->costo_base_unitario_override];
        return $this->calculatePayload($payload, $user, ['tasa_iva' => $proforma->tasa_iva, 'tasa_ir' => $proforma->tasa_ir, 'tasa_comision_vendedor' => $proforma->tasa_comision_vendedor, 'service_overrides' => $proforma->servicios->mapWithKeys(fn ($service) => [(string) $service->servicio_adicional_id => $service->costo_base_unitario_override])->all()]);
    }

    public function materialPurchaseBreakdown(array $snapshots): array
    {
        $groups = [];
        foreach ($snapshots as $snapshot) { $key = (string) $snapshot['material_id']; if (!isset($groups[$key])) $groups[$key] = ['material_id' => $snapshot['material_id'], 'nombre' => $snapshot['material_nombre_snapshot'], 'unidad_consumo' => $snapshot['unidad_consumo_snapshot'], 'cantidad_requerida' => '0.0000', 'unidad_compra' => $snapshot['unidad_compra_snapshot'], 'contenido_por_compra' => (string) $snapshot['contenido_por_compra_snapshot'], 'cantidad_compra' => 0, 'precio_compra' => (string) $snapshot['precio_compra_snapshot'], 'costo_consumo' => '0.00', 'costo_compra' => '0.00']; $groups[$key]['cantidad_requerida'] = DecimalMoney::add($groups[$key]['cantidad_requerida'], (string) $snapshot['cantidad_total_con_merma'], 4); $groups[$key]['costo_consumo'] = DecimalMoney::add($groups[$key]['costo_consumo'], (string) $snapshot['costo_consumo_total']); }
        $total = '0.00'; foreach ($groups as &$group) { $group['cantidad_compra'] = DecimalMoney::ceilUnits($group['cantidad_requerida'], $group['contenido_por_compra']); $group['costo_compra'] = DecimalMoney::mul((string) $group['cantidad_compra'], $group['precio_compra']); $total = DecimalMoney::add($total, $group['costo_compra']); } unset($group); return ['items' => array_values($groups), 'total' => $total];
    }

    private function sum(array $items, string $key): string { $total = '0.00'; foreach ($items as $item) $total = DecimalMoney::add($total, (string) $item[$key]); return $total; }
    private function assertDiscount(string $discount, string $gross, string $message): void { if (DecimalMoney::compare($discount, '0') < 0 || DecimalMoney::compare($discount, $gross) > 0) throw new BusinessRuleException($message, [], 422); }
    private function materialSnapshot($material, string $base, string $factor, $override, string $origin, $originId): array { $waste = $override === null ? (string) $material->porcentaje_merma : (string) $override; $content = (string) $material->contenido_por_compra; $unit = DecimalMoney::div((string) $material->precio_actual, $content, 6); $totalQty = DecimalMoney::mul(DecimalMoney::mul($base, DecimalMoney::add('1', DecimalMoney::div($waste, '100', 6), 6), 4), $factor, 4); return ['origen_tipo' => $origin, 'origen_id' => $originId, 'material_id' => $material->id, 'material_nombre_snapshot' => $material->nombre, 'unidad_consumo_snapshot' => $material->unidad_consumo, 'unidad_compra_snapshot' => $material->unidad_compra, 'cantidad_base_unitaria' => $base, 'factor_cantidad' => $factor, 'porcentaje_merma' => $waste, 'cantidad_total_con_merma' => $totalQty, 'contenido_por_compra_snapshot' => $content, 'precio_compra_snapshot' => (string) $material->precio_actual, 'costo_unidad_consumo_snapshot' => $unit, 'costo_consumo_total' => DecimalMoney::mul($totalQty, $unit)]; }
    private function laborSnapshot($process, string $cost, string $factor, string $origin, $originId, $order): array { return ['origen_tipo' => $origin, 'origen_id' => $originId, 'proceso_produccion_id' => $process?->id, 'proceso_nombre_snapshot' => $process?->nombre ?? 'Proceso', 'costo_unitario_snapshot' => $cost, 'factor_cantidad' => $factor, 'costo_total' => DecimalMoney::mul($cost, $factor), 'orden' => $order]; }
}
