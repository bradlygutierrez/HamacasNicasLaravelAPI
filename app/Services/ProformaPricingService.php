<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Hamaca;
use App\Models\Proforma;
use App\Models\ServicioAdicional;
use App\Models\Usuario;

class ProformaPricingService
{
    public function __construct(private readonly CostoProduccionService $costoService)
    {
    }

    public function calculatePayload(array $payload, Usuario $user): array
    {
        $productSubtotal = 0.0; $serviceSubtotal = 0.0; $lineDiscounts = 0.0; $materialCost = 0.0; $laborCost = 0.0; $serviceBaseCost = 0.0;
        $materialSnapshots = []; $laborSnapshots = []; $details = []; $generalServices = [];

        foreach ($payload['detalles'] ?? [] as $detailIndex => $input) {
            $hamaca = Hamaca::with(['categoria', 'tamano', 'recetaActiva.detallesMateriales.material', 'recetaActiva.detallesManoObra.proceso'])->findOrFail($input['hamaca_id']);
            $recipe = $hamaca->recetaActiva;
            if (!$recipe) throw new BusinessRuleException("El modelo {$hamaca->nombre} no tiene una fórmula activa.", [], 422);
            $quantity = (int) $input['cantidad']; $price = (float) ($input['precio_unitario'] ?? $hamaca->precio); $discount = (float) ($input['descuento'] ?? 0);
            $gross = $this->money($quantity * $price); if ($discount > $gross) throw new BusinessRuleException('El descuento de línea no puede superar el importe bruto.', [], 422);
            $subtotal = $this->money($gross - $discount); $recipeCosts = $this->costoService->calcularReceta($recipe); $recipeCost = (float) $recipeCosts['resumen']['costo_produccion'];
            $productSubtotal += $subtotal; $lineDiscounts += $discount; $materialCost += (float) $recipeCosts['resumen']['costo_materiales'] * $quantity; $laborCost += (float) $recipeCosts['resumen']['costo_mano_obra'] * $quantity;
            $line = ['input' => $input, 'hamaca' => $hamaca, 'recipe' => $recipe, 'cantidad' => $quantity, 'precio_unitario' => $price, 'descuento' => $discount, 'subtotal' => $subtotal, 'costo_unitario' => $recipeCost, 'costo_total' => $this->money($recipeCost * $quantity), 'services' => []];
            foreach ($recipe->detallesMateriales as $component) $materialSnapshots[] = $this->materialSnapshot($component->material, (float) $component->cantidad, $quantity, $component->porcentaje_merma, 'receta', $detailIndex);
            foreach ($recipe->detallesManoObra as $component) $laborSnapshots[] = $this->laborSnapshot($component->proceso, (float) $component->costo_unitario, $quantity, 'receta', $detailIndex, $component->orden);
            foreach ($input['servicios'] ?? [] as $serviceInput) {
                $service = ServicioAdicional::with(['materiales.material', 'manoObra.proceso'])->where('state', true)->findOrFail($serviceInput['servicio_adicional_id']);
                if ($service->alcance !== 'producto') throw new BusinessRuleException('El servicio seleccionado no aplica a productos.', [], 422);
                $serviceQuantity = (float) $serviceInput['cantidad']; if ($service->metodo_calculo === 'por_producto' && $serviceQuantity > $quantity) throw new BusinessRuleException('La cantidad del servicio por producto supera la cantidad de hamacas.', [], 422);
                $servicePrice = (float) ($serviceInput['precio_unitario'] ?? $service->precio_venta_actual); $serviceDiscount = (float) ($serviceInput['descuento'] ?? 0); $serviceGross = $this->money($serviceQuantity * $servicePrice); if ($serviceDiscount > $serviceGross) throw new BusinessRuleException('El descuento del servicio no puede superar su importe.', [], 422);
                $serviceCost = $this->costoService->calcularServicio($service); $costUnit = (float) $serviceCost['resumen']['costo_total']; $serviceSubtotal += $this->money($serviceGross - $serviceDiscount); $lineDiscounts += $serviceDiscount; $serviceBaseCost += (float) $service->costo_actual * $serviceQuantity;
                $line['services'][] = ['service' => $service, 'input' => $serviceInput, 'cantidad' => $serviceQuantity, 'precio_unitario' => $servicePrice, 'descuento' => $serviceDiscount, 'subtotal' => $this->money($serviceGross - $serviceDiscount), 'costo_unitario' => $costUnit, 'costo_total' => $this->money($costUnit * $serviceQuantity)];
                foreach ($service->materiales as $component) $materialSnapshots[] = $this->materialSnapshot($component->material, (float) $component->cantidad, $serviceQuantity, $component->porcentaje_merma, 'servicio_producto', $detailIndex);
                foreach ($service->manoObra as $component) $laborSnapshots[] = $this->laborSnapshot($component->proceso, (float) $component->costo_unitario, $serviceQuantity, 'servicio_producto', $detailIndex, $component->orden);
            }
            $details[] = $line;
        }

        foreach ($payload['servicios_pedido'] ?? [] as $index => $input) {
            $service = ServicioAdicional::with(['materiales.material', 'manoObra.proceso'])->where('state', true)->findOrFail($input['servicio_adicional_id']);
            if ($service->alcance !== 'pedido') throw new BusinessRuleException('El servicio seleccionado no aplica al pedido.', [], 422);
            $quantity = (float) $input['cantidad']; $price = (float) ($input['precio_unitario'] ?? $service->precio_venta_actual); $discount = (float) ($input['descuento'] ?? 0); $gross = $this->money($quantity * $price); if ($discount > $gross) throw new BusinessRuleException('El descuento del servicio no puede superar su importe.', [], 422);
            $baseOverride = $input['costo_base_unitario_override'] ?? null; if ($user->rol === 'vendedor') $baseOverride = null;
            $serviceCost = $this->costoService->calcularServicio($service); $costUnit = $this->money(($baseOverride === null ? (float) $service->costo_actual : (float) $baseOverride) + (float) $serviceCost['resumen']['costo_materiales'] + (float) $serviceCost['resumen']['costo_mano_obra']);
            $serviceSubtotal += $this->money($gross - $discount); $lineDiscounts += $discount; $serviceBaseCost += ($baseOverride === null ? (float) $service->costo_actual : (float) $baseOverride) * $quantity;
            $generalServices[] = ['service' => $service, 'input' => $input, 'cantidad' => $quantity, 'precio_unitario' => $price, 'descuento' => $discount, 'subtotal' => $this->money($gross - $discount), 'costo_unitario' => (float) $costUnit, 'costo_total' => $this->money((float) $costUnit * $quantity)];
            foreach ($service->materiales as $component) $materialSnapshots[] = $this->materialSnapshot($component->material, (float) $component->cantidad, $quantity, $component->porcentaje_merma, 'servicio_pedido', $index);
            foreach ($service->manoObra as $component) $laborSnapshots[] = $this->laborSnapshot($component->proceso, (float) $component->costo_unitario, $quantity, 'servicio_pedido', $index, $component->orden);
        }

        $grossSubtotal = $this->money($productSubtotal + $serviceSubtotal); $globalDiscount = (float) ($payload['descuento'] ?? 0); if ($globalDiscount > $grossSubtotal - $lineDiscounts) throw new BusinessRuleException('El descuento global no puede superar la base comercial.', [], 422);
        $base = $this->money($grossSubtotal - $globalDiscount); $ivaRate = (float) ($payload['tasa_iva'] ?? config('proformas.iva_rate')); $irRate = (float) ($payload['tasa_ir'] ?? config('proformas.ir_rate')); $commissionRate = $user->rol === 'vendedor' ? (float) config('proformas.commission_rate') : (float) ($payload['tasa_comision_vendedor'] ?? config('proformas.commission_rate'));
        $iva = !empty($payload['aplica_iva']) ? $this->money($base * $ivaRate / 100) : 0.0; $ir = !empty($payload['aplica_ir']) ? $this->money($base * $irRate / 100) : 0.0; $commission = $this->money($base * $commissionRate / 100); $total = $this->money($base + $iva - $ir); $materialCost = $this->money($materialCost); $laborCost = $this->money($laborCost); $serviceBaseCost = $this->money($serviceBaseCost); $totalCost = $this->money($materialCost + $laborCost + $serviceBaseCost); $purchaseCost = $this->purchaseCost($materialSnapshots); $utility = $this->money($base - $totalCost - $commission);
        return ['details' => $details, 'services' => $generalServices, 'material_snapshots' => $materialSnapshots, 'labor_snapshots' => $laborSnapshots, 'values' => ['subtotal_productos' => $this->money($productSubtotal), 'subtotal_servicios' => $this->money($serviceSubtotal), 'subtotal_bruto' => $grossSubtotal, 'descuento' => $this->money($globalDiscount + $lineDiscounts), 'base_neta' => $base, 'tasa_iva' => $ivaRate, 'monto_iva' => $iva, 'tasa_ir' => $irRate, 'monto_ir' => $ir, 'tasa_comision_vendedor' => $commissionRate, 'monto_comision_vendedor' => $commission, 'costo_materiales_estimado' => $materialCost, 'costo_mano_de_obra_estimado' => $laborCost, 'costo_servicios_base_estimado' => $serviceBaseCost, 'costo_total_estimado' => $totalCost, 'costo_compra_estimado' => $purchaseCost, 'utilidad_estimada' => $utility, 'total' => $total]];
    }

    public function calculateProforma(Proforma $proforma, Usuario $user): array
    {
        $proforma->load(['detalles.servicios', 'servicios']);
        $payload = ['descuento' => $proforma->descuento, 'aplica_iva' => $proforma->aplica_iva, 'tasa_iva' => $proforma->tasa_iva, 'aplica_ir' => $proforma->aplica_ir, 'tasa_ir' => $proforma->tasa_ir, 'tasa_comision_vendedor' => $proforma->tasa_comision_vendedor, 'detalles' => [], 'servicios_pedido' => []];
        foreach ($proforma->detalles as $detail) $payload['detalles'][] = ['hamaca_id' => $detail->hamaca_id, 'hamaca_variante_id' => $detail->hamaca_variante_id, 'cantidad' => $detail->cantidad, 'precio_unitario' => $detail->precio_unitario, 'descuento' => $detail->descuento, 'servicios' => $detail->servicios->map(fn ($service) => ['servicio_adicional_id' => $service->servicio_adicional_id, 'cantidad' => $service->cantidad, 'detalle' => $service->detalle, 'precio_unitario' => $service->precio_unitario, 'descuento' => $service->descuento])->all()];
        foreach ($proforma->servicios as $service) $payload['servicios_pedido'][] = ['servicio_adicional_id' => $service->servicio_adicional_id, 'cantidad' => $service->cantidad, 'detalle' => $service->detalle, 'precio_unitario' => $service->precio_unitario, 'descuento' => $service->descuento, 'costo_base_unitario_override' => $service->costo_base_unitario_snapshot];
        return $this->calculatePayload($payload, $user);
    }

    private function money(float $value): float { return round($value, 2, PHP_ROUND_HALF_UP); }
    private function materialSnapshot($material, float $base, float $factor, $override, string $origin, int $originId): array { $waste = $override === null ? (float) $material->porcentaje_merma : (float) $override; $content = (float) $material->contenido_por_compra; $unit = (float) $material->precio_actual / $content; $totalQty = $base * (1 + $waste / 100) * $factor; return ['origen_tipo' => $origin, 'origen_id' => $originId, 'material_id' => $material->id, 'material_nombre_snapshot' => $material->nombre, 'unidad_consumo_snapshot' => $material->unidad_consumo, 'unidad_compra_snapshot' => $material->unidad_compra, 'cantidad_base_unitaria' => $base, 'factor_cantidad' => $factor, 'porcentaje_merma' => $waste, 'cantidad_total_con_merma' => $totalQty, 'contenido_por_compra_snapshot' => $content, 'precio_compra_snapshot' => $material->precio_actual, 'costo_unidad_consumo_snapshot' => $unit, 'costo_consumo_total' => $this->money($totalQty * $unit)]; }
    private function laborSnapshot($process, float $cost, float $factor, string $origin, int $originId, $order): array { return ['origen_tipo' => $origin, 'origen_id' => $originId, 'proceso_produccion_id' => $process?->id, 'proceso_nombre_snapshot' => $process?->nombre ?? 'Proceso', 'costo_unitario_snapshot' => $cost, 'factor_cantidad' => $factor, 'costo_total' => $this->money($cost * $factor), 'orden' => $order]; }
    private function purchaseCost(array $snapshots): float { $groups = []; foreach ($snapshots as $snapshot) { $key = (string) $snapshot['material_id']; $groups[$key] = ($groups[$key] ?? 0) + (float) $snapshot['cantidad_total_con_merma']; } $total = 0; foreach ($groups as $id => $quantity) { $material = \App\Models\Material::find($id); if ($material) $total += ceil($quantity / (float) $material->contenido_por_compra) * (float) $material->precio_actual; } return $this->money($total); }
}
