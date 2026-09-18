<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class MaterialService
{
    public function create(array $data, ?Usuario $usuario = null): Material
    {
        return DB::transaction(function () use ($data, $usuario): Material {
            $data = $this->normalizeData($data);

            $material = Material::create([
                ...$data,
                'porcentaje_merma' => $data['porcentaje_merma'] ?? 0,
                'state' => true,
            ]);

            $material->precios()->create([
                'precio' => $material->precio_actual,
                'fecha' => now(),
                'usuario_id' => $usuario?->id,
            ]);

            return $material->fresh();
        });
    }

    public function update(Material $material, array $data, ?Usuario $usuario = null): Material
    {
        return DB::transaction(function () use ($material, $data, $usuario): Material {
            $data = $this->normalizeData($data, $material);
            $material->fill($data);
            $priceChanged = $material->isDirty('precio_actual');
            $material->save();

            if ($priceChanged) {
                $material->precios()->create([
                    'precio' => $material->precio_actual,
                    'fecha' => now(),
                    'usuario_id' => $usuario?->id,
                ]);
            }

            return $material->fresh();
        });
    }

    public function deactivate(Material $material): Material
    {
        $material->update(['state' => false]);

        return $material->fresh();
    }

    private function normalizeData(array $data, ?Material $material = null): array
    {
        if ($material === null || array_key_exists('porcentaje_merma', $data)) {
            $data['porcentaje_merma'] = $data['porcentaje_merma'] ?? 0;
        }

        $consumo = $data['unidad_consumo'] ?? $material?->unidad_consumo;
        $compra = $data['unidad_compra'] ?? $material?->unidad_compra;

        if ($consumo === $compra) {
            $data['contenido_por_compra'] = 1;
        }

        return $data;
    }
}
