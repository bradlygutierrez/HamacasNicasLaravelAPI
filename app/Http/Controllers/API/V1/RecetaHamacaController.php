<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRecetaHamacaRequest;
use App\Http\Resources\V1\RecetaHamacaResource;
use App\Models\HamacaVariante;
use App\Models\RecetaHamaca;
use App\Services\CostoProduccionService;
use App\Services\RecetaHamacaService;
use Illuminate\Http\Request;

class RecetaHamacaController extends Controller
{
    public function __construct(
        private readonly RecetaHamacaService $recetaService,
        private readonly CostoProduccionService $costoService
    ) {
    }

    public function index(HamacaVariante $hamacaVariante)
    {
        return RecetaHamacaResource::collection(
            $hamacaVariante->recetas()->with(['hamaca', 'hamacaVariante', 'detallesMateriales.material', 'detallesManoObra.proceso'])->latest('version')->get()
        );
    }

    public function active(HamacaVariante $hamacaVariante)
    {
        $recipe = $hamacaVariante->recetaActiva()->with(['hamaca', 'hamacaVariante', 'detallesMateriales.material', 'detallesManoObra.proceso'])->first();

        return $recipe ? new RecetaHamacaResource($recipe) : response()->json(['data' => null]);
    }

    public function store(Request $request, HamacaVariante $hamacaVariante)
    {
        $request->validate(['source_variant_id' => ['nullable', 'integer']]);
        $sourceVariantId = $request->integer('source_variant_id') ?: null;

        return (new RecetaHamacaResource($this->recetaService->crearBorrador($hamacaVariante, $request->user(), $sourceVariantId)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RecetaHamaca $recetaHamaca)
    {
        return new RecetaHamacaResource($recetaHamaca->load([
            'hamaca.categoria',
            'hamaca.tamano',
            'hamacaVariante.colores',
            'detallesMateriales.material',
            'detallesManoObra.proceso',
        ]));
    }

    public function update(UpdateRecetaHamacaRequest $request, RecetaHamaca $recetaHamaca)
    {
        return new RecetaHamacaResource($this->recetaService->updateDraft($recetaHamaca, $request->validated()));
    }

    public function costs(RecetaHamaca $recetaHamaca)
    {
        return response()->json(['data' => $this->costoService->calcularReceta($recetaHamaca)]);
    }

    public function activate(Request $request, RecetaHamaca $recetaHamaca)
    {
        return new RecetaHamacaResource($this->recetaService->activate($recetaHamaca, $request->user()));
    }

    public function discard(RecetaHamaca $recetaHamaca)
    {
        return new RecetaHamacaResource($this->recetaService->discard($recetaHamaca));
    }
}
