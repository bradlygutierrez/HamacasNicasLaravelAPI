<?php

namespace App\Services;

use App\Models\ServicioAdicional;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class ServicioAdicionalService
{
    public function create(array $data, ?Usuario $usuario = null): ServicioAdicional
    {
        return DB::transaction(function () use ($data, $usuario): ServicioAdicional {
            $servicio = ServicioAdicional::create([
                ...$data,
                'state' => true,
            ]);

            $servicio->precios()->create([
                'precio_venta' => $servicio->precio_venta_actual,
                'costo' => $servicio->costo_actual,
                'fecha' => now(),
                'usuario_id' => $usuario?->id,
            ]);

            return $servicio->fresh();
        });
    }

    public function update(ServicioAdicional $servicio, array $data, ?Usuario $usuario = null): ServicioAdicional
    {
        return DB::transaction(function () use ($servicio, $data, $usuario): ServicioAdicional {
            $servicio->fill($data);
            $pricesChanged = $servicio->isDirty(['precio_venta_actual', 'costo_actual']);
            $servicio->save();

            if ($pricesChanged) {
                $servicio->precios()->create([
                    'precio_venta' => $servicio->precio_venta_actual,
                    'costo' => $servicio->costo_actual,
                    'fecha' => now(),
                    'usuario_id' => $usuario?->id,
                ]);
            }

            return $servicio->fresh();
        });
    }

    public function deactivate(ServicioAdicional $servicio): ServicioAdicional
    {
        $servicio->update(['state' => false]);

        return $servicio->fresh();
    }
}
