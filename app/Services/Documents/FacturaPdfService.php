<?php

namespace App\Services\Documents;

use App\Models\Factura;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class FacturaPdfService
{
    private const LABELS = [
        'es' => [
            'invoice_title' => 'FACTURA',
            'date' => 'Fecha',
            'invoice_number' => 'Número de factura',
            'sender' => 'Emisor',
            'receiver' => 'Receptor',
            'quantity' => 'Cant.',
            'description' => 'Descripción',
            'total' => 'Total',
            'seller' => 'Vendedor',
            'channel' => 'Canal',
            'payment_method' => 'Método de pago',
            'origin' => 'Origen',
            'order' => 'Pedido',
            'direct_sale' => 'Venta directa',
            'unit_price' => 'Precio unitario',
            'discount' => 'Descuento',
            'colors' => 'Colores',
            'approximate' => 'Aprox.',
            'subtotal' => 'Subtotal',
            'vat' => 'IVA',
            'income_tax' => 'IR',
            'total_to_pay' => 'Total a pagar',
            'thank_you' => 'Gracias por su compra.',
        ],
        'en' => [
            'invoice_title' => 'INVOICE',
            'date' => 'Date',
            'invoice_number' => 'Invoice number',
            'sender' => 'Sender',
            'receiver' => 'Receiver',
            'quantity' => 'Qty.',
            'description' => 'Description',
            'total' => 'Total',
            'seller' => 'Seller',
            'channel' => 'Channel',
            'payment_method' => 'Payment method',
            'origin' => 'Origin',
            'order' => 'Order',
            'direct_sale' => 'Direct sale',
            'unit_price' => 'Unit price',
            'discount' => 'Discount',
            'colors' => 'Colors',
            'approximate' => 'Approx.',
            'subtotal' => 'Subtotal',
            'vat' => 'VAT',
            'income_tax' => 'Income tax withholding',
            'total_to_pay' => 'Total to pay',
            'thank_you' => 'Thank you for your purchase.',
        ],
    ];

    private const VALUE_LABELS = [
        'es' => [
            'channels' => ['pos' => 'POS', 'ecommerce' => 'E-commerce'],
            'payment_methods' => ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia'],
        ],
        'en' => [
            'channels' => ['pos' => 'Point of sale', 'ecommerce' => 'E-commerce'],
            'payment_methods' => ['efectivo' => 'Cash', 'tarjeta' => 'Card', 'transferencia' => 'Bank transfer'],
        ],
    ];

    public function __construct(private readonly PdfImageResolver $images) {}

    public function render(Factura $factura, string $language = 'es')
    {
        return Pdf::loadView('pdf.factura.document', $this->viewModel($factura, $language))->setPaper('a4', 'landscape');
    }

    public function viewModel(Factura $factura, string $language = 'es'): array
    {
        if (!isset(self::LABELS[$language])) throw new \InvalidArgumentException('El idioma de la factura debe ser es o en.');
        $factura->loadMissing(['usuario', 'pedido', 'detalles.servicios.pedidoDetalleServicio.proformaDetalleServicio.desglose', 'servicios.pedidoServicio.proformaServicio.desglose']);
        $company = config('documentos.empresa');
        $company['logo'] = $this->images->resolve($company['logo'] ?? null) ?? $this->defaultLogo();
        foreach (['firma', 'sello'] as $key) $company[$key] = $this->images->resolve($company[$key] ?? null);
        $details = $factura->detalles->map(fn ($detail) => [
            'quantity' => $detail->cantidad,
            'name' => $detail->hamaca_nombre,
            'description' => $detail->hamaca_descripcion,
            'colors' => $this->decodeColors($detail->colores_snapshot),
            'unit_price' => $detail->precio_unitario,
            'discount' => $detail->descuento,
            'subtotal' => $detail->subtotal,
            'services' => $detail->servicios->map(fn ($service) => ['name' => $service->servicio_nombre_snapshot, 'detail' => $service->detalle, 'quantity' => $service->cantidad, 'unit_price' => $service->precio_unitario, 'has_approximate_unit_price' => $service->pedidoDetalleServicio?->proformaDetalleServicio?->desglose?->isNotEmpty() ?? false, 'discount' => $service->descuento, 'subtotal' => $service->subtotal])->all(),
        ])->all();
        $services = $factura->servicios->map(fn ($service) => ['name' => $service->servicio_nombre_snapshot, 'detail' => $service->detalle, 'quantity' => $service->cantidad, 'unit_price' => $service->precio_unitario, 'has_approximate_unit_price' => $service->pedidoServicio?->proformaServicio?->desglose?->isNotEmpty() ?? false, 'discount' => $service->descuento, 'subtotal' => $service->subtotal])->all();
        $labels = self::LABELS[$language];
        $valueLabels = self::VALUE_LABELS[$language];
        $dateFormatted = null;
        if ($factura->fecha) {
            $date = Carbon::parse($factura->fecha)->locale($language);
            $dateFormatted = $language === 'en'
                ? $date->translatedFormat('l, F j, Y')
                : $date->translatedFormat('l, j \d\e F \d\e Y');
        }

        return compact('factura', 'details', 'services', 'company', 'labels', 'dateFormatted') + [
            'documentLocale' => $language,
            'channelLabel' => $factura->canal ? ($valueLabels['channels'][$factura->canal] ?? $factura->canal) : '—',
            'paymentMethodLabel' => $factura->metodo_pago ? ($valueLabels['payment_methods'][$factura->metodo_pago] ?? $factura->metodo_pago) : '—',
            'currency' => config('documentos.moneda', 'C$'),
        ];
    }

    private function defaultLogo(): string
    {
        $path = public_path('Logo.svg');
        if (!is_file($path)) throw new \RuntimeException('No se encontró el logo predeterminado para las facturas.');
        $svg = file_get_contents($path);
        if ($svg === false) throw new \RuntimeException('No se pudo leer el logo predeterminado para las facturas.');
        $svg = str_replace(['viewBox="0 0 612 792"', '#f4f4f4'], ['viewBox="0 350 612 150"', '#002060'], $svg);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function decodeColors($value): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : array_filter([(string) $value]);
    }
}
