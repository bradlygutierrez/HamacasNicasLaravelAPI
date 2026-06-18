<?php

namespace App\Services;

use App\Models\InventarioHamaca;
use Illuminate\Support\Facades\DB;

class InventarioService
{
    public function compositionKey(array $colorIds): string
    {
        $ids = array_values(array_unique(array_map('intval', $colorIds)));
        sort($ids);

        return hash('sha256', implode(',', $ids));
    }

    public function upsert(array $data): InventarioHamaca
    {
        return DB::transaction(function () use ($data) {
            $composicionClave = $this->compositionKey($data['color_ids']);

            $inventario = InventarioHamaca::where('hamaca_id', $data['hamaca_id'])
                ->where('usuario_id', $data['usuario_id'])
                ->where('ubicacion_id', $data['ubicacion_id'])
                ->where('composicion_clave', $composicionClave)
                ->lockForUpdate()
                ->first();

            if ($inventario) {
                $inventario->increment('cantidad', $data['cantidad']);
            } else {
                $inventario = InventarioHamaca::create([
                    'hamaca_id' => $data['hamaca_id'],
                    'usuario_id' => $data['usuario_id'],
                    'ubicacion_id' => $data['ubicacion_id'],
                    'composicion_clave' => $composicionClave,
                    'cantidad' => $data['cantidad'],
                ]);
            }

            $inventario->colores()->sync($data['color_ids']);

            return $inventario->load(['hamaca.categoria', 'hamaca.tamano', 'ubicacion', 'usuario', 'colores']);
        });
    }

    public function transfer(int $inventarioId, int $cantidad, ?int $ubicacionDestinoId = null): InventarioHamaca
    {
        return DB::transaction(function () use ($inventarioId, $cantidad, $ubicacionDestinoId) {
            $origen = InventarioHamaca::with('colores')
                ->lockForUpdate()
                ->findOrFail($inventarioId);

            if ($origen->cantidad < $cantidad) {
                throw new \RuntimeException('Stock insuficiente.');
            }

            $origen->decrement('cantidad', $cantidad);

            if ($ubicacionDestinoId !== null) {
                $origen->ubicacion_id = $ubicacionDestinoId;
                $origen->save();
            }

            return $origen->fresh(['hamaca.categoria', 'hamaca.tamano', 'ubicacion', 'usuario', 'colores']);
        });
    }
}
