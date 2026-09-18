<?php

namespace App\Services;

use App\Models\RecetaHamaca;
use App\Models\ServicioAdicional;

class CostoProduccionService
{
    public function calcularReceta(RecetaHamaca $recipe): array
    {
        $recipe->loadMissing(['detallesMateriales.material', 'detallesManoObra.proceso']);
        $materials = [];
        $materialTotal = 0.0;

        foreach ($recipe->detallesMateriales as $detail) {
            $material = $detail->material;
            $merma = (float) ($detail->porcentaje_merma ?? $material->porcentaje_merma ?? 0);
            $quantity = (float) $detail->cantidad;
            $content = (float) $material->contenido_por_compra;
            $unitCost = (float) $material->precio_actual / $content;
            $withWaste = $quantity * (1 + ($merma / 100));
            $cost = $withWaste * $unitCost;
            $materialTotal += $cost;
            $materials[] = [
                'material_id' => $material->id,
                'nombre' => $material->nombre,
                'cantidad_base' => number_format($quantity, 4, '.', ''),
                'unidad_consumo' => $material->unidad_consumo,
                'merma' => number_format($merma, 2, '.', ''),
                'cantidad_con_merma' => number_format($withWaste, 4, '.', ''),
                'unidad_compra' => $material->unidad_compra,
                'contenido_por_compra' => number_format($content, 4, '.', ''),
                'precio_compra' => number_format((float) $material->precio_actual, 2, '.', ''),
                'costo_unidad_consumo' => number_format($unitCost, 2, '.', ''),
                'costo' => number_format($cost, 2, '.', ''),
            ];
        }

        $laborTotal = 0.0;
        $labor = $recipe->detallesManoObra->map(function ($detail) use (&$laborTotal): array {
            $cost = (float) $detail->costo_unitario;
            $laborTotal += $cost;
            return [
                'proceso_produccion_id' => $detail->proceso_produccion_id,
                'nombre' => $detail->proceso?->nombre,
                'costo' => number_format($cost, 2, '.', ''),
                'orden' => $detail->orden,
            ];
        })->values()->all();

        return [
            'materiales' => $materials,
            'mano_obra' => $labor,
            'resumen' => [
                'costo_materiales' => number_format($materialTotal, 2, '.', ''),
                'costo_mano_obra' => number_format($laborTotal, 2, '.', ''),
                'costo_produccion' => number_format($materialTotal + $laborTotal, 2, '.', ''),
            ],
        ];
    }

    public function calcularServicio(ServicioAdicional $service): array
    {
        $service->loadMissing(['materiales.material', 'manoObra.proceso']);
        $materialTotal = 0.0;
        $materials = [];

        foreach ($service->materiales as $detail) {
            $material = $detail->material;
            $merma = (float) ($detail->porcentaje_merma ?? $material->porcentaje_merma ?? 0);
            $quantity = (float) $detail->cantidad;
            $unitCost = (float) $material->precio_actual / (float) $material->contenido_por_compra;
            $withWaste = $quantity * (1 + ($merma / 100));
            $cost = $withWaste * $unitCost;
            $materialTotal += $cost;
            $materials[] = [
                'material_id' => $material->id,
                'nombre' => $material->nombre,
                'cantidad_base' => number_format($quantity, 4, '.', ''),
                'unidad_consumo' => $material->unidad_consumo,
                'merma' => number_format($merma, 2, '.', ''),
                'cantidad_con_merma' => number_format($withWaste, 4, '.', ''),
                'costo' => number_format($cost, 2, '.', ''),
            ];
        }

        $laborTotal = (float) $service->manoObra->sum('costo_unitario');
        $total = (float) $service->costo_actual + $materialTotal + $laborTotal;

        return [
            'materiales' => $materials,
            'mano_obra' => $service->manoObra->map(fn ($detail) => [
                'proceso_produccion_id' => $detail->proceso_produccion_id,
                'nombre' => $detail->proceso?->nombre,
                'costo' => number_format((float) $detail->costo_unitario, 2, '.', ''),
                'orden' => $detail->orden,
            ])->values()->all(),
            'resumen' => [
                'costo_base' => number_format((float) $service->costo_actual, 2, '.', ''),
                'costo_materiales' => number_format($materialTotal, 2, '.', ''),
                'costo_mano_obra' => number_format($laborTotal, 2, '.', ''),
                'costo_total' => number_format($total, 2, '.', ''),
                'precio_venta' => number_format((float) $service->precio_venta_actual, 2, '.', ''),
            ],
        ];
    }
}
