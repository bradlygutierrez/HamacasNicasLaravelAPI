<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Http\Resources\V1\FacturaResource;
use App\Http\Resources\V1\FacturaCollection;
use Illuminate\Http\Request;
use App\Services\Documents\FacturaPdfService;

class FacturaController extends Controller
{
    public function __construct(private readonly FacturaPdfService $pdfs) {}
    /**
     * Display a listing of the resource.
     */
    public function index(\Illuminate\Http\Request $request)
    {
        if ($request->user()->rol === 'almacenista') abort(403, 'No tenés permiso para consultar facturas.');
        $query = Factura::with(['cliente', 'usuario', 'pedido', 'detalles.servicios', 'servicios'])
            ->when($request->user()->rol === 'vendedor', fn ($q) => $q->where('vendedor_id', $request->user()->id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(fn ($inner) => $inner
                    ->where('numero', 'like', "%{$search}%")
                    ->orWhere('nombre_cliente', 'like', "%{$search}%"));
            })
            ->when($request->filled('origen'), fn ($q) => $q->where('origen', $request->string('origen')->toString()))
            ->latest();

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return new FacturaCollection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function show(\Illuminate\Http\Request $request, Factura $factura)
    {
        $this->assertAccess($request, $factura);
        return new FacturaResource($factura->load(['cliente', 'usuario', 'pedido', 'detalles.servicios', 'servicios']));
    }

    public function pdf(Request $request, Factura $factura)
    {
        $this->assertAccess($request, $factura);
        return response($this->pdfs->render($factura)->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline') . '; filename="' . $factura->numero . '.pdf"',
        ]);
    }

    private function assertAccess(Request $request, Factura $factura): void
    {
        if ($request->user()->rol === 'almacenista' || ($request->user()->rol === 'vendedor' && $factura->vendedor_id !== $request->user()->id)) abort(403, 'No tenés acceso a esta factura.');
    }
}
