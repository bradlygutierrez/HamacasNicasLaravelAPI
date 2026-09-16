<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\HamacaVariante;
use App\Models\InventarioHamaca;
use App\Models\Movimiento;
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

    public function entrada(array $data, int $operadorId): InventarioHamaca
    {
        return DB::transaction(function () use ($data, $operadorId) {
            $variante = HamacaVariante::with('colores')
                ->lockForUpdate()
                ->findOrFail($data['hamaca_variante_id']);

            $colorIds = $variante->colores->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

            $inventario = InventarioHamaca::where('hamaca_variante_id', $variante->id)
                ->where('usuario_id', $data['usuario_id'])
                ->where('ubicacion_id', $data['ubicacion_id'])
                ->lockForUpdate()
                ->first();

            if ($inventario) {
                $inventario->increment('cantidad', $data['cantidad']);
            } else {
                $inventario = InventarioHamaca::create([
                    'hamaca_id' => $variante->hamaca_id,
                    'hamaca_variante_id' => $variante->id,
                    'usuario_id' => $data['usuario_id'],
                    'ubicacion_id' => $data['ubicacion_id'],
                    'composicion_clave' => $variante->composicion_clave,
                    'cantidad' => $data['cantidad'],
                ]);
            }

            $inventario->colores()->sync($colorIds);

            Movimiento::create([
                'inventario_hamaca_id' => $inventario->id,
                'usuario_id' => $operadorId,
                'ubicacion_destino_id' => $data['ubicacion_id'],
                'tipo' => 'entrada',
                'cantidad' => $data['cantidad'],
                'fecha' => $data['fecha'] ?? now(),
            ]);

            return $this->loadInventario($inventario);
        });
    }

    public function salida(int $inventarioId, int $cantidad, int $operadorId, ?string $fecha = null): InventarioHamaca
    {
        return DB::transaction(function () use ($inventarioId, $cantidad, $operadorId, $fecha) {
            $inventario = InventarioHamaca::with(['variante.colores', 'colores'])
                ->lockForUpdate()
                ->findOrFail($inventarioId);

            $this->ensureStock($inventario, $cantidad);

            $inventario->decrement('cantidad', $cantidad);

            Movimiento::create([
                'inventario_hamaca_id' => $inventario->id,
                'usuario_id' => $operadorId,
                'ubicacion_origen_id' => $inventario->ubicacion_id,
                'tipo' => 'salida',
                'cantidad' => $cantidad,
                'fecha' => $fecha ?? now(),
            ]);

            return $this->loadInventario($inventario);
        });
    }

    public function transfer(int $inventarioId, int $cantidad, int $ubicacionDestinoId, int $operadorId, ?string $fecha = null): InventarioHamaca
    {
        return DB::transaction(function () use ($inventarioId, $cantidad, $ubicacionDestinoId, $operadorId, $fecha) {
            $origen = InventarioHamaca::with(['variante.colores', 'colores'])
                ->lockForUpdate()
                ->findOrFail($inventarioId);

            $this->ensureStock($origen, $cantidad);

            if ((int) $origen->ubicacion_id === $ubicacionDestinoId) {
                throw new BusinessRuleException('La ubicación destino debe ser diferente a la ubicación origen.', [
                    'ubicacion_destino_id' => ['La ubicación destino debe ser diferente a la ubicación origen.'],
                ], 422);
            }

            $destino = InventarioHamaca::where('hamaca_variante_id', $origen->hamaca_variante_id)
                ->where('usuario_id', $origen->usuario_id)
                ->where('ubicacion_id', $ubicacionDestinoId)
                ->lockForUpdate()
                ->first();

            $origen->decrement('cantidad', $cantidad);

            if ($destino) {
                $destino->increment('cantidad', $cantidad);
            } else {
                $destino = InventarioHamaca::create([
                    'hamaca_id' => $origen->hamaca_id,
                    'hamaca_variante_id' => $origen->hamaca_variante_id,
                    'usuario_id' => $origen->usuario_id,
                    'ubicacion_id' => $ubicacionDestinoId,
                    'composicion_clave' => $origen->composicion_clave,
                    'cantidad' => $cantidad,
                ]);
            }

            $colorIds = $origen->variante
                ? $origen->variante->colores->pluck('id')->all()
                : $origen->colores->pluck('id')->all();

            $destino->colores()->sync($colorIds);

            Movimiento::create([
                'inventario_hamaca_id' => $origen->id,
                'usuario_id' => $operadorId,
                'ubicacion_origen_id' => $origen->ubicacion_id,
                'ubicacion_destino_id' => $ubicacionDestinoId,
                'tipo' => 'transferencia',
                'cantidad' => $cantidad,
                'fecha' => $fecha ?? now(),
            ]);

            return $this->loadInventario($origen);
        });
    }

    private function ensureStock(InventarioHamaca $inventario, int $cantidad): void
    {
        if ($inventario->cantidad < $cantidad) {
            throw new BusinessRuleException('Stock insuficiente.', [
                'cantidad' => ["Solo hay {$inventario->cantidad} unidades disponibles."],
            ]);
        }
    }

    private function loadInventario(InventarioHamaca $inventario): InventarioHamaca
    {
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
    }
}
