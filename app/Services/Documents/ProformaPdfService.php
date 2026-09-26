<?php

namespace App\Services\Documents;

use App\Models\Proforma;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ProformaPdfService
{
    public function __construct(private readonly PdfImageResolver $images) {}

    public function render(Proforma $proforma)
    {
        return Pdf::loadView('pdf.proforma.document', $this->viewModel($proforma))->setPaper('a4', 'portrait');
    }

    public function viewModel(Proforma $proforma): array
    {
        $proforma->loadMissing(['vendedor', 'detalles.servicios', 'detalles.hamaca.categoria', 'detalles.hamaca.tamano', 'detalles.hamaca.fotos', 'detalles.hamaca.colores', 'servicios']);
        $details = $proforma->detalles->map(fn ($detail) => [
            'quantity' => $detail->cantidad,
            'name' => $detail->hamaca_nombre_snapshot,
            'description' => $detail->hamaca_descripcion_snapshot,
            'unit_price' => $detail->precio_unitario,
            'discount' => $detail->descuento,
            'subtotal' => $detail->subtotal,
            'services' => $detail->servicios->map(fn ($service) => $this->serviceLine($service))->all(),
        ])->all();
        $sheets = [];
        foreach ($proforma->detalles->groupBy(fn ($detail) => $detail->hamaca_id !== null ? (string) $detail->hamaca_id : 'legacy-detail-' . $detail->id) as $group) {
            $detail = $group->first();
            $resolvedPhotos = $this->images->resolveMany($detail->hamaca?->fotos?->sortBy('id') ?? collect());
            $sheets[] = [
                'name' => $detail->hamaca_nombre_snapshot,
                'colors' => $detail->hamaca?->colores?->pluck('nombre')->all() ?? [],
                'category' => $detail->hamaca?->categoria?->nombre,
                'size' => $detail->hamaca?->tamano?->nombre,
                'quantity' => $group->sum('cantidad'),
                'description' => $detail->hamaca_descripcion_snapshot,
                'photos' => $resolvedPhotos,
            ];
        }
        $company = config('documentos.empresa');
        foreach (['logo', 'firma', 'sello'] as $key) $company[$key] = $this->images->resolve($company[$key] ?? null);
        return compact('proforma', 'details', 'sheets', 'company') + ['number' => $proforma->numero ?: 'Borrador', 'dateFormatted' => $this->formatDate($proforma->fecha), 'isDraft' => $proforma->estado === 'borrador', 'currency' => config('documentos.moneda', 'C$'), 'paymentConditions' => config('documentos.proforma_condiciones_pago')];
    }

    private function formatDate($date): ?string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : null;
    }

    private function serviceLine($service): array
    {
        return ['name' => $service->servicio_nombre_snapshot, 'detail' => $service->detalle, 'quantity' => $service->cantidad, 'unit_price' => $service->precio_unitario, 'discount' => $service->descuento, 'subtotal' => $service->subtotal];
    }
}
