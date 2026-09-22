<?php

namespace App\Http\Controllers\API\V1;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Hamaca;
use Illuminate\Http\Request;
use App\Http\Resources\V1\HamacaResource;
use App\Http\Resources\V1\HamacaCollection;

class HamacaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $query = Hamaca::with(['categoria', 'tamano'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%' . $request->string('search') . '%';
                $query->where('nombre', 'like', $term);
            })
            ->latest();

        return new HamacaCollection($query->paginate($perPage));
    }

    public function getHamacasWithDetails()
    {
        $hamacas = Hamaca::with([
            'categoria',
            'tamano',
            'fotos',

            'variantes.colores',
            'variantes.fotos',

            'inventarios.colores',
            'inventarios.usuario',
            'inventarios.ubicacion',
            'inventarios.variante.colores',
            'inventarios.variante.fotos',
        ])->latest()->paginate();
        return new HamacaCollection($hamacas);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validación simple
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string',
            'categoria_id' => 'required|integer|exists:categorias,id',
            'tamano_id' => 'required|integer|exists:tamanos,id',
            'precio' => 'required|numeric|min:0',
        ]);

        $hamaca = Hamaca::create($validated);

        return response()->json([
            'message' => 'Hamaca creada correctamente',
            'data' => new HamacaResource($hamaca)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Hamaca $hamaca)
    {
        $hamaca->load([
            'categoria',
            'tamano',
            'fotos'
        ]);

        return new HamacaResource($hamaca);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Hamaca $hamaca)
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:100',
            'descripcion' => 'nullable|string',
            'categoria_id' => 'sometimes|integer|exists:categorias,id',
            'tamano_id' => 'sometimes|integer|exists:tamanos,id',
            'precio' => 'sometimes|numeric|min:0',
        ]);

        $hamaca->update($validated);

        return response()->json([
            'message' => 'Hamaca actualizada correctamente',
            'data' => new HamacaResource($hamaca)
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Hamaca $hamaca)
    {
        $hasInventario = $hamaca->inventarios()->exists();
        $hasVariantes = $hamaca->variantes()->exists();

        if ($hasInventario || $hasVariantes) {
            $hamaca->delete();

            return response()->json([
                'message' => 'Modelo desactivado correctamente. Tiene variantes o inventario relacionado, por eso se aplicó borrado lógico.',
            ]);
        }

        $hamaca->delete();

        return response()->json([
            'message' => 'Modelo eliminado correctamente.',
        ]);
    }
    public function getMonthlyInventory()
    {
        $total = DB::table('inventario_hamacas')
            ->sum('cantidad');

        return response()->json([
            'total' => $total
        ]);
    }
}
