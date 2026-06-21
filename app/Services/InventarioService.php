<?php

namespace App\Services;

use App\Models\HamacaVariante;
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
            $variante = null;

            if (!empty($data['hamaca_variante_id'])) {
                $variante = HamacaVariante::with('colores')
                    ->lockForUpdate()
                    ->findOrFail($data['hamaca_variante_id']);

                $hamacaId = $variante->hamaca_id;
                $colorIds = $variante->colores->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                $composicionClave = $variante->composicion_clave;
            } else {
                $hamacaId = (int) $data['hamaca_id'];
                $colorIds = array_values(array_unique(array_map('intval', $data['color_ids'])));
                sort($colorIds);

                $composicionClave = $this->compositionKey($colorIds);

                $variante = HamacaVariante::where('hamaca_id', $hamacaId)
                    ->where('composicion_clave', $composicionClave)
                    ->lockForUpdate()
                    ->first();

                if (!$variante) {
                    $variante = new HamacaVariante();
                    $variante->hamaca_id = $hamacaId;
                    $variante->composicion_clave = $composicionClave;
                    $variante->state = true;
                    $variante->save();
                    $variante->colores()->sync($colorIds);
                }
            }

            $inventario = InventarioHamaca::where('hamaca_variante_id', $variante->id)
                ->where('usuario_id', $data['usuario_id'])
                ->where('ubicacion_id', $data['ubicacion_id'])
                ->lockForUpdate()
                ->first();

            if ($inventario) {
                $inventario->increment('cantidad', $data['cantidad']);
            } else {
                $inventario = InventarioHamaca::create([
                    'hamaca_id' => $hamacaId,
                    'hamaca_variante_id' => $variante->id,
                    'usuario_id' => $data['usuario_id'],
                    'ubicacion_id' => $data['ubicacion_id'],
                    'composicion_clave' => $composicionClave,
                    'cantidad' => $data['cantidad'],
                ]);
            }

            $inventario->colores()->sync($colorIds);

            return $inventario->fresh([
                'hamaca.categoria',
                'hamaca.tamano',
                'hamaca.fotos',
                'variante.colores',
                'variante.fotos',
                'ubicacion',
                'usuario',
                'colores',
            ]);
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

            return $origen->fresh([
                'hamaca.categoria',
                'hamaca.tamano',
                'hamaca.fotos',
                'variante.colores',
                'variante.fotos',
                'ubicacion',
                'usuario',
                'colores',
            ]);
        });
    }
}