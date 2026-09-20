<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Factura;
use App\Models\HamacaVariante;
use App\Models\InventarioHamaca;
use App\Models\Movimiento;
use App\Models\Pedido;
use App\Models\Usuario;
use App\Support\DecimalMoney;
use Illuminate\Support\Facades\DB;

class PedidoFacturacionService
{
    public function facturar(Pedido $pedido, Usuario $user, array $data): array
    {
        return DB::transaction(function () use ($pedido, $user, $data): array {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);
            $this->authorize($pedido, $user);
            $existing = $pedido->factura()->with($this->invoiceRelations())->first();
            if ($existing) return [$existing, false];
            if ($pedido->estado !== 'terminado') throw new BusinessRuleException('Solo un pedido terminado puede facturarse.', [], 409);

            $locationId = (int) $data['ubicacion_id'];
            $inventoryUserId = $user->rol === 'vendedor' ? $user->id : (int) ($data['usuario_inventario_id'] ?? $user->id);
            $lines = $this->resolveLines($pedido, $data['lineas'], $locationId, $inventoryUserId);
            $inventories = $this->produce($pedido, $lines, $user->id, $locationId, $inventoryUserId);
            $invoice = $this->createInvoice($pedido, $data);

            foreach ($lines as $line) {
                $inventory = $inventories[$line['detail']->id];
                $inventory->decrement('cantidad', $line['detail']->cantidad);
                $invoiceDetail = $invoice->detalles()->create([
                    'pedido_detalle_id' => $line['detail']->id,
                    'inventario_hamaca_id' => $inventory->id,
                    'hamaca_id' => $line['detail']->hamaca_id,
                    'usuario_id' => $inventoryUserId,
                    'ubicacion_id' => $locationId,
                    'hamaca_nombre' => $line['detail']->hamaca_nombre_snapshot,
                    'hamaca_descripcion' => $line['detail']->hamaca_descripcion_snapshot,
                    'cantidad' => $line['detail']->cantidad,
                    'precio_unitario' => $line['detail']->precio_unitario,
                    'descuento' => $line['detail']->descuento,
                    'subtotal' => $line['detail']->subtotal,
                    'colores_snapshot' => json_encode($line['variant']->colores->pluck('nombre')->values()->all()),
                ]);
                foreach ($line['detail']->servicios as $service) $invoiceDetail->servicios()->create(['pedido_detalle_servicio_id' => $service->id, 'servicio_adicional_id' => $service->servicio_adicional_id, 'servicio_nombre_snapshot' => $service->servicio_nombre_snapshot, 'detalle' => $service->detalle, 'cantidad' => $service->cantidad, 'precio_unitario' => $service->precio_unitario, 'descuento' => $service->descuento, 'subtotal' => $service->subtotal]);
                $this->movement($inventory->id, $user->id, $invoice->id, $pedido->id, $line['detail']->cantidad, $locationId, 'salida');
            }
            foreach ($pedido->servicios as $service) $invoice->servicios()->create(['pedido_servicio_id' => $service->id, 'servicio_adicional_id' => $service->servicio_adicional_id, 'servicio_nombre_snapshot' => $service->servicio_nombre_snapshot, 'detalle' => $service->detalle, 'cantidad' => $service->cantidad, 'precio_unitario' => $service->precio_unitario, 'descuento' => $service->descuento, 'subtotal' => $service->subtotal]);
            $pedido->update(['facturado_at' => now()]);
            return [$invoice->fresh($this->invoiceRelations()), true];
        });
    }

    private function authorize(Pedido $pedido, Usuario $user): void
    {
        if (!in_array($user->rol, ['admin', 'vendedor'], true)) throw new BusinessRuleException('No tenés permiso para facturar pedidos.', [], 403);
        if ($user->rol === 'vendedor' && $pedido->vendedor_id !== $user->id) throw new BusinessRuleException('No tenés acceso a este pedido.', [], 403);
    }

    private function resolveLines(Pedido $pedido, array $requested, int $locationId, int $inventoryUserId): array
    {
        $details = $pedido->detalles()->with('servicios')->lockForUpdate()->get()->keyBy('id');
        $lines = [];
        foreach ($requested as $input) {
            $detail = $details->get((int) $input['pedido_detalle_id']);
            if (!$detail) throw new BusinessRuleException('La línea no pertenece al pedido.', [], 422);
            $variantId = $detail->hamaca_variante_id ?? ($input['hamaca_variante_id'] ?? null);
            if (!$variantId) throw new BusinessRuleException('Cada línea debe tener una variante física.', ['lineas' => ['Falta resolver la variante del producto.']], 422);
            $variant = HamacaVariante::with('colores')->lockForUpdate()->find($variantId);
            if (!$variant || (int) $variant->hamaca_id !== (int) $detail->hamaca_id || (!$detail->hamaca_variante_id && !$variant->state)) throw new BusinessRuleException('La variante no corresponde al producto.', ['lineas' => ['La variante seleccionada no es válida.']], 422);
            if ($detail->hamaca_variante_id && (int) $detail->hamaca_variante_id !== (int) $variant->id) throw new BusinessRuleException('La variante del pedido no puede reemplazarse.', [], 422);
            $lines[] = ['detail' => $detail, 'variant' => $variant, 'location_id' => $locationId, 'inventory_user_id' => $inventoryUserId];
        }
        if (count($lines) !== $details->count()) throw new BusinessRuleException('Debe resolverse cada línea del pedido.', [], 422);
        return $lines;
    }

    private function produce(Pedido $pedido, array $lines, int $operatorId, int $locationId, int $inventoryUserId): array
    {
        $inventories = [];
        foreach ($lines as $line) {
            $detail = $line['detail']; $variant = $line['variant'];
            $inventory = InventarioHamaca::where('hamaca_variante_id', $variant->id)->where('usuario_id', $inventoryUserId)->where('ubicacion_id', $locationId)->lockForUpdate()->first();
            if (!$inventory) $inventory = InventarioHamaca::create(['hamaca_id' => $variant->hamaca_id, 'hamaca_variante_id' => $variant->id, 'usuario_id' => $inventoryUserId, 'ubicacion_id' => $locationId, 'composicion_clave' => $variant->composicion_clave, 'cantidad' => 0]);
            $inventory->increment('cantidad', $detail->cantidad);
            $inventory->colores()->sync($variant->colores->pluck('id')->all());
            $this->movement($inventory->id, $operatorId, null, $pedido->id, $detail->cantidad, $locationId, 'entrada');
            $inventories[$detail->id] = $inventory->fresh();
        }
        return $inventories;
    }

    private function createInvoice(Pedido $pedido, array $data): Factura
    {
        $invoice = Factura::create(['numero' => 'PENDING-' . uniqid('', true), 'pedido_id' => $pedido->id, 'origen' => 'pedido', 'cliente_id' => $pedido->cliente_id, 'vendedor_id' => $pedido->vendedor_id, 'canal' => $data['canal'], 'nombre_cliente' => $pedido->nombre_cliente, 'ruc' => $pedido->ruc, 'direccion' => $pedido->direccion, 'telefono' => $pedido->telefono, 'correo' => $pedido->correo, 'metodo_pago' => $data['metodo_pago'] ?? null, 'subtotal' => $pedido->subtotal_bruto, 'descuento' => $pedido->descuento_total, 'tasa_iva' => DecimalMoney::div((string) $pedido->tasa_iva, '100', 4), 'aplica_iva' => $pedido->aplica_iva, 'monto_iva' => $pedido->monto_iva, 'aplica_ir' => $pedido->aplica_ir, 'tasa_ir' => DecimalMoney::div((string) $pedido->tasa_ir, '100', 4), 'monto_ir' => $pedido->monto_ir, 'total' => $pedido->total, 'fecha' => now()]);
        $invoice->update(['numero' => 'FAC-' . str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT)]);
        return $invoice;
    }

    private function movement(int $inventoryId, int $operatorId, ?int $invoiceId, int $pedidoId, int $quantity, int $locationId, string $type): void
    {
        Movimiento::create(['inventario_hamaca_id' => $inventoryId, 'usuario_id' => $operatorId, 'factura_id' => $invoiceId, 'pedido_id' => $pedidoId, 'ubicacion_origen_id' => $type === 'salida' ? $locationId : null, 'ubicacion_destino_id' => $type === 'entrada' ? $locationId : null, 'tipo' => $type, 'cantidad' => $quantity, 'fecha' => now()]);
    }

    private function invoiceRelations(): array { return ['cliente', 'usuario', 'pedido', 'detalles.servicios', 'servicios']; }
}
