<?php

namespace App\Services\Documents;

use App\Models\Pedido;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PedidoMaterialPdfService
{
    public function __construct(private readonly PdfImageResolver $images) {}

    public function render(Pedido $pedido)
    {
        return Pdf::loadView('pdf.pedido-materiales', $this->viewModel($pedido))
            ->setPaper('letter', 'portrait');
    }

    public function viewModel(Pedido $pedido): array
    {
        $pedido->loadMissing(['vendedor', 'materiales']);
        $company = config('documentos.empresa');
        $company['logo'] = $this->images->resolve($company['logo'] ?: 'Logo.svg');

        return [
            'pedido' => $pedido,
            'materials' => $pedido->materiales->sortBy('material_nombre_snapshot')->values(),
            'company' => $company,
            'generatedAt' => Carbon::now()->format('d/m/Y H:i'),
        ];
    }
}
