<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\InventarioHamaca;
use App\Models\Movimiento;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VentaService
{
    public function __construct(private readonly InventarioService $inventarioService)
    {
    }

    public function crearVenta(array $data): Factura
    {
        return DB::transaction(function () use ($data) {
            $items = collect($data['items']);
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
                    throw new RuntimeException('Inventario no encontrado.');
                }

                if ($inventario->cantidad < $item['cantidad']) {
                    throw new RuntimeException('Stock insuficiente.');
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
            $base = max(0, round($subtotal - $descuento, 2));
            $tasaIva = 0.15;
            $aplicaIr = (bool) ($data['aplica_ir'] ?? false);
            $tasaIr = 0.02;
            $montoIva = round($base * $tasaIva, 2);
            $montoIr = $aplicaIr ? round($base * $tasaIr, 2) : 0.00;
            $total = round($base + $montoIva - $montoIr, 2);

            $factura = Factura::create([
                'numero' => $this->generateInvoiceNumber(),
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

    private function generateInvoiceNumber(): string
    {
        $next = (Factura::max('id') ?? 0) + 1;

        return 'FAC-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
