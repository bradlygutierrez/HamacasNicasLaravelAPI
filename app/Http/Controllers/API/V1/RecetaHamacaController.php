<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRecetaHamacaRequest;
use App\Http\Resources\V1\RecetaHamacaResource;
use App\Models\Hamaca;
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

    public function index(Hamaca $hamaca)
    {
        return RecetaHamacaResource::collection(
            $hamaca->recetas()->with(['hamaca.categoria', 'hamaca.tamano', 'hamaca.colores', 'detallesMateriales.material', 'detallesManoObra.proceso'])->latest('version')->get()
        );
    }

    public function active(Hamaca $hamaca)
    {
        $recipe = $hamaca->recetaActiva()->with(['hamaca.categoria', 'hamaca.tamano', 'hamaca.colores', 'detallesMateriales.material', 'detallesManoObra.proceso'])->first();

        return $recipe ? new RecetaHamacaResource($recipe) : response()->json(['data' => null]);
    }

    public function store(Request $request, Hamaca $hamaca)
    {
        $request->validate(['source_hamaca_id' => ['nullable', 'integer', 'exists:hamacas,id']]);
        $sourceHamacaId = $request->integer('source_hamaca_id') ?: null;

        return (new RecetaHamacaResource($this->recetaService->crearBorrador($hamaca, $request->user(), $sourceHamacaId)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RecetaHamaca $recetaHamaca)
    {
        return new RecetaHamacaResource($recetaHamaca->load([
            'hamaca.categoria',
            'hamaca.tamano',
            'hamaca.colores',
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
