<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Hamaca;
use App\Models\InventarioHamaca;
use App\Models\Movimiento;
use Illuminate\Support\Facades\DB;

class InventarioService
{
    public function entrada(array $data, int $operadorId): InventarioHamaca
    {
        return DB::transaction(function () use ($data, $operadorId) {
            Hamaca::with('colores')->lockForUpdate()->findOrFail($data['hamaca_id']);
            $inventory = InventarioHamaca::where('hamaca_id', $data['hamaca_id'])->where('usuario_id', $data['usuario_id'])->where('ubicacion_id', $data['ubicacion_id'])->lockForUpdate()->first();
            if ($inventory) $inventory->increment('cantidad', $data['cantidad']);
            else $inventory = InventarioHamaca::create(['hamaca_id' => $data['hamaca_id'], 'usuario_id' => $data['usuario_id'], 'ubicacion_id' => $data['ubicacion_id'], 'cantidad' => $data['cantidad']]);
            Movimiento::create(['inventario_hamaca_id' => $inventory->id, 'usuario_id' => $operadorId, 'ubicacion_destino_id' => $data['ubicacion_id'], 'tipo' => 'entrada', 'cantidad' => $data['cantidad'], 'fecha' => $data['fecha'] ?? now()]);
            return $this->loadInventario($inventory);
        });
    }
    public function salida(int $inventarioId, int $cantidad, int $operadorId, ?string $fecha = null): InventarioHamaca
    {
        return DB::transaction(function () use ($inventarioId, $cantidad, $operadorId, $fecha) {
            $inventory = InventarioHamaca::lockForUpdate()->findOrFail($inventarioId); $this->ensureStock($inventory, $cantidad); $inventory->decrement('cantidad', $cantidad);
            Movimiento::create(['inventario_hamaca_id' => $inventory->id, 'usuario_id' => $operadorId, 'ubicacion_origen_id' => $inventory->ubicacion_id, 'tipo' => 'salida', 'cantidad' => $cantidad, 'fecha' => $fecha ?? now()]);
            return $this->loadInventario($inventory);
        });
    }
    public function transfer(int $inventarioId, int $cantidad, int $ubicacionDestinoId, int $operadorId, ?string $fecha = null): InventarioHamaca
    {
        return DB::transaction(function () use ($inventarioId, $cantidad, $ubicacionDestinoId, $operadorId, $fecha) {
            $source = InventarioHamaca::lockForUpdate()->findOrFail($inventarioId); $this->ensureStock($source, $cantidad);
            if ((int) $source->ubicacion_id === $ubicacionDestinoId) throw new BusinessRuleException('La ubicación destino debe ser diferente a la ubicación origen.', [], 422);
            $target = InventarioHamaca::where('hamaca_id', $source->hamaca_id)->where('usuario_id', $source->usuario_id)->where('ubicacion_id', $ubicacionDestinoId)->lockForUpdate()->first();
            $source->decrement('cantidad', $cantidad);
            if ($target) $target->increment('cantidad', $cantidad); else $target = InventarioHamaca::create(['hamaca_id' => $source->hamaca_id, 'usuario_id' => $source->usuario_id, 'ubicacion_id' => $ubicacionDestinoId, 'cantidad' => $cantidad]);
            Movimiento::create(['inventario_hamaca_id' => $source->id, 'usuario_id' => $operadorId, 'ubicacion_origen_id' => $source->ubicacion_id, 'ubicacion_destino_id' => $ubicacionDestinoId, 'tipo' => 'transferencia', 'cantidad' => $cantidad, 'fecha' => $fecha ?? now()]);
            return $this->loadInventario($source);
        });
    }
    private function ensureStock(InventarioHamaca $inventory, int $quantity): void { if ($inventory->cantidad < $quantity) throw new BusinessRuleException('Stock insuficiente.', ['cantidad' => ["Solo hay {$inventory->cantidad} unidades disponibles."]]); }
    private function loadInventario(InventarioHamaca $inventory): InventarioHamaca { return $inventory->fresh(['hamaca.categoria', 'hamaca.tamano', 'hamaca.fotos', 'hamaca.colores', 'ubicacion', 'usuario']); }
}
