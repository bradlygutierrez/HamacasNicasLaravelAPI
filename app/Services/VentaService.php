<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Factura;
use App\Models\InventarioHamaca;
use App\Models\Movimiento;
use App\Support\DecimalMoney;
use Illuminate\Support\Facades\DB;

class VentaService
{
    public function calcular(array $data): array
    {
        return DB::transaction(fn () => $this->calcularDentroDeTransaccion($data));
    }

    public function crearVenta(array $data): Factura
    {
        return DB::transaction(function () use ($data) {
            $calculo = $this->calcularDentroDeTransaccion($data);
            $factura = Factura::create([
                'numero' => 'PENDING-'.uniqid('', true),
                'pedido_id' => null,
                'origen' => 'venta_directa',
                'cliente_id' => $data['cliente_id'] ?? null,
                'vendedor_id' => $data['vendedor_id'],
                'canal' => $data['canal'],
                'nombre_cliente' => $data['nombre_cliente'],
                'ruc' => $data['ruc'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'correo' => $data['correo'] ?? null,
                'metodo_pago' => $data['metodo_pago'] ?? null,
                'subtotal' => $calculo['subtotal'],
                'descuento' => $calculo['descuento'],
                'tasa_iva' => $calculo['tasa_iva'],
                'aplica_iva' => $calculo['aplica_iva'],
                'monto_iva' => $calculo['monto_iva'],
                'aplica_ir' => $calculo['aplica_ir'],
                'tasa_ir' => $calculo['tasa_ir'],
                'monto_ir' => $calculo['monto_ir'],
                'total' => $calculo['total'],
                'fecha' => now(),
            ]);

            $factura->numero = 'FAC-'.str_pad((string) $factura->id, 6, '0', STR_PAD_LEFT);
            $factura->save();

            foreach ($calculo['lineas'] as $linea) {
                $inventario = $linea['inventario'];
                $inventario->decrement('cantidad', $linea['cantidad']);
                $factura->detalles()->create([
                    'inventario_hamaca_id' => $inventario->id,
                    'hamaca_id' => $inventario->hamaca_id,
                    'usuario_id' => $inventario->usuario_id,
                    'ubicacion_id' => $inventario->ubicacion_id,
                    'hamaca_nombre' => $inventario->hamaca->nombre,
                    'hamaca_descripcion' => $inventario->hamaca->descripcion,
                    'colores_snapshot' => json_encode($inventario->colores->pluck('nombre')->values()->all()),
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'subtotal' => $linea['subtotal'],
                ]);
                Movimiento::create([
                    'inventario_hamaca_id' => $inventario->id,
                    'usuario_id' => $data['vendedor_id'],
                    'factura_id' => $factura->id,
                    'pedido_id' => null,
                    'ubicacion_origen_id' => $inventario->ubicacion_id,
                    'ubicacion_destino_id' => $inventario->ubicacion_id,
                    'tipo' => 'salida',
                    'cantidad' => $linea['cantidad'],
                    'fecha' => now(),
                ]);
            }

            return $factura->load(['cliente', 'usuario', 'pedido', 'detalles.servicios', 'servicios']);
        });
    }

    private function calcularDentroDeTransaccion(array $data): array
    {
        $items = collect($data['items'])->map(fn (array $item) => [
            'inventario_hamaca_id' => (int) $item['inventario_hamaca_id'],
            'cantidad' => (int) $item['cantidad'],
        ]);
        $inventarios = InventarioHamaca::with(['hamaca', 'colores', 'usuario', 'ubicacion'])
            ->whereIn('id', $items->pluck('inventario_hamaca_id')->all())
            ->lockForUpdate()->get()->keyBy('id');
        $subtotal = '0.00';
        $lineas = [];

        foreach ($items as $item) {
            $inventario = $inventarios->get($item['inventario_hamaca_id']);
            if (!$inventario) {
                throw new BusinessRuleException('Inventario no encontrado.', ['items' => ['El inventario seleccionado no existe.']], 422);
            }
            if ((int) $inventario->cantidad < $item['cantidad']) {
                throw new BusinessRuleException('Stock insuficiente.', ['items' => ["Solo hay {$inventario->cantidad} unidades disponibles."]]);
            }
            $precioUnitario = (string) $inventario->hamaca->precio;
            $lineaSubtotal = DecimalMoney::mul($precioUnitario, (string) $item['cantidad']);
            $subtotal = DecimalMoney::add($subtotal, $lineaSubtotal);
            $lineas[] = ['inventario' => $inventario, 'cantidad' => $item['cantidad'], 'precio_unitario' => $precioUnitario, 'subtotal' => $lineaSubtotal];
        }

        $descuento = DecimalMoney::add((string) ($data['descuento'] ?? '0'), '0');
        if (DecimalMoney::compare($descuento, $subtotal) > 0) {
            throw new BusinessRuleException('El descuento no puede ser mayor que el subtotal.', ['descuento' => ['El descuento no puede ser mayor que el subtotal.']]);
        }
        $base = DecimalMoney::sub($subtotal, $descuento);
        $aplicaIva = (bool) ($data['aplica_iva'] ?? true);
        $aplicaIr = (bool) ($data['aplica_ir'] ?? false);
        $ivaRate = (string) config('comercial.iva_rate', '15');
        $irRate = (string) config('comercial.ir_rate', '2');
        $tasaIva = DecimalMoney::div($ivaRate, '100', 4);
        $tasaIr = DecimalMoney::div($irRate, '100', 4);
        $montoIva = $aplicaIva ? DecimalMoney::percent($base, $ivaRate) : '0.00';
        $montoIr = $aplicaIr ? DecimalMoney::percent($base, $irRate) : '0.00';

        return [
            'lineas' => $lineas,
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'base' => $base,
            'aplica_iva' => $aplicaIva,
            'tasa_iva' => $tasaIva,
            'monto_iva' => $montoIva,
            'aplica_ir' => $aplicaIr,
            'tasa_ir' => $tasaIr,
            'monto_ir' => $montoIr,
            'total' => DecimalMoney::sub(DecimalMoney::add($base, $montoIva), $montoIr),
        ];
    }
}
