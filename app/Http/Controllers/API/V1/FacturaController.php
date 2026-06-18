<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Http\Resources\V1\FacturaResource;
use App\Http\Resources\V1\FacturaCollection;
use Illuminate\Http\Request;

class FacturaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return new FacturaCollection(
            Factura::with(['cliente', 'usuario', 'detalles'])->latest()->paginate()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function show(Factura $factura)
    {
        return new FacturaResource($factura->load(['cliente', 'usuario', 'detalles']));
    }
}
