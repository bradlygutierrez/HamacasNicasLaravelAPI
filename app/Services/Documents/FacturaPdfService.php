<?php

namespace App\Services\Documents;

use App\Models\Factura;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class FacturaPdfService
{
    public function __construct(private readonly PdfImageResolver $images) {}

    public function render(Factura $factura)
    {
        return Pdf::loadView('pdf.factura.document', $this->viewModel($factura))->setPaper('a4', 'portrait');
    }

    public function viewModel(Factura $factura): array
    {
        $factura->loadMissing(['usuario', 'pedido', 'detalles.servicios', 'servicios']);
        $company = config('documentos.empresa');
        foreach (['logo', 'firma', 'sello'] as $key) $company[$key] = $this->images->resolve($company[$key] ?? null);
        $details = $factura->detalles->map(fn ($detail) => [
            'quantity' => $detail->cantidad,
            'name' => $detail->hamaca_nombre,
            'description' => $detail->hamaca_descripcion,
            'colors' => $this->decodeColors($detail->colores_snapshot),
            'unit_price' => $detail->precio_unitario,
            'discount' => $detail->descuento,
            'subtotal' => $detail->subtotal,
            'services' => $detail->servicios->map(fn ($service) => ['name' => $service->servicio_nombre_snapshot, 'detail' => $service->detalle, 'quantity' => $service->cantidad, 'unit_price' => $service->precio_unitario, 'discount' => $service->descuento, 'subtotal' => $service->subtotal])->all(),
        ])->all();
        $services = $factura->servicios->map(fn ($service) => ['name' => $service->servicio_nombre_snapshot, 'detail' => $service->detalle, 'quantity' => $service->cantidad, 'unit_price' => $service->precio_unitario, 'discount' => $service->descuento, 'subtotal' => $service->subtotal])->all();
        return compact('factura', 'details', 'services', 'company') + ['dateFormatted' => $factura->fecha ? Carbon::parse($factura->fecha)->format('d/m/Y') : null, 'currency' => config('documentos.moneda', 'C$')];
    }

    private function decodeColors($value): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : array_filter([(string) $value]);
    }
}
