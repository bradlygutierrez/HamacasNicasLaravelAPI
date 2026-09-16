<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Factura;
use App\Models\InventarioHamaca;
use App\Models\Movimiento;
use Illuminate\Support\Facades\DB;

class VentaService
{
    public function __construct(private readonly InventarioService $inventarioService)
    {
    }

    public function crearVenta(array $data): Factura
    {
        return DB::transaction(function () use ($data) {
            $items = collect($data['items'])
                ->groupBy('inventario_hamaca_id')
                ->map(fn ($lines, $inventarioId) => [
                    'inventario_hamaca_id' => (int) $inventarioId,
                    'cantidad' => (int) $lines->sum('cantidad'),
                ])
                ->values();
            $subtotal = 0.0;
            $detalleData = [];

            $inventarios = InventarioHamaca::with(['hamaca', 'colores', 'usuario', 'ubicacion'])
                ->whereIn('id', $items->pluck('inventario_hamaca_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $inventario = $inventarios->get($item['inventario_hamaca_id']);

                if (!$inventario) {
                    throw new BusinessRuleException('Inventario no encontrado.', [
                        'items' => ['El inventario seleccionado no existe.'],
                    ], 422);
                }

                if ($inventario->cantidad < $item['cantidad']) {
                    throw new BusinessRuleException('Stock insuficiente.', [
                        'items' => ["Solo hay {$inventario->cantidad} unidades disponibles."],
                    ]);
                }

                $precioUnitario = (float) $inventario->hamaca->precio;
                $lineaSubtotal = round($precioUnitario * $item['cantidad'], 2);
                $subtotal += $lineaSubtotal;

                $detalleData[] = [
                    'inventario' => $inventario,
                    'cantidad' => (int) $item['cantidad'],
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $lineaSubtotal,
                ];
            }

            $descuento = round((float) ($data['descuento'] ?? 0), 2);

            if ($descuento > round($subtotal, 2)) {
                throw new BusinessRuleException('El descuento no puede ser mayor que el subtotal.', [
                    'descuento' => ['El descuento no puede ser mayor que el subtotal.'],
                ]);
            }

            $base = round($subtotal - $descuento, 2);
            $tasaIva = 0.15;
            $aplicaIr = (bool) ($data['aplica_ir'] ?? false);
            $tasaIr = 0.02;
            $montoIva = round($base * $tasaIva, 2);
            $montoIr = $aplicaIr ? round($base * $tasaIr, 2) : 0.00;
            $total = round($base + $montoIva - $montoIr, 2);

            $factura = Factura::create([
                'numero' => 'PENDING-'.uniqid('', true),
                'cliente_id' => $data['cliente_id'] ?? null,
                'vendedor_id' => $data['vendedor_id'],
                'canal' => $data['canal'],
                'nombre_cliente' => $data['nombre_cliente'],
                'ruc' => $data['ruc'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'correo' => $data['correo'] ?? null,
                'metodo_pago' => $data['metodo_pago'] ?? null,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'tasa_iva' => $tasaIva,
                'monto_iva' => $montoIva,
                'aplica_ir' => $aplicaIr,
                'tasa_ir' => $tasaIr,
                'monto_ir' => $montoIr,
                'total' => $total,
                'fecha' => now(),
            ]);

            $factura->numero = $this->generateInvoiceNumber($factura->id);
            $factura->save();

            foreach ($detalleData as $linea) {
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
                    'ubicacion_origen_id' => $inventario->ubicacion_id,
                    'ubicacion_destino_id' => $inventario->ubicacion_id,
                    'tipo' => 'salida',
                    'cantidad' => $linea['cantidad'],
                    'fecha' => now(),
                ]);
            }

            return $factura->load(['cliente', 'usuario', 'detalles']);
        });
    }

    private function generateInvoiceNumber(int $facturaId): string
    {
        return 'FAC-' . str_pad((string) $facturaId, 6, '0', STR_PAD_LEFT);
    }
}
