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
}
