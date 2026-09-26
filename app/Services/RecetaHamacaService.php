<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Hamaca;
use App\Models\RecetaHamaca;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class RecetaHamacaService
{
    public function crearBorrador(Hamaca $hamaca, Usuario $usuario, ?int $sourceHamacaId = null): RecetaHamaca
    {
        return DB::transaction(function () use ($hamaca, $usuario, $sourceHamacaId) {
            $target = Hamaca::query()->lockForUpdate()->findOrFail($hamaca->id);
            if (RecetaHamaca::where('hamaca_id', $target->id)->where('estado', 'borrador')->exists()) {
                throw new BusinessRuleException('La hamaca ya tiene una fórmula en borrador.', [], 422);
            }
            $active = RecetaHamaca::with(['detallesMateriales', 'detallesManoObra'])->where('hamaca_id', $target->id)->where('estado', 'activa')->first();
            if ($sourceHamacaId !== null && $active) throw new BusinessRuleException('La hamaca ya tiene fórmula activa; la nueva versión debe clonarse de su propia fórmula.', [], 422);
            $source = $target;
            if ($sourceHamacaId !== null) {
                $source = Hamaca::query()->where('categoria_id', $target->categoria_id)->where('tamano_id', $target->tamano_id)->find($sourceHamacaId);
                if (!$source) throw new BusinessRuleException('La hamaca origen debe tener la misma categoría y tamaño.', [], 422);
                $active = RecetaHamaca::with(['detallesMateriales', 'detallesManoObra'])->where('hamaca_id', $source->id)->where('estado', 'activa')->first();
                if (!$active) throw new BusinessRuleException('La hamaca origen no tiene fórmula activa.', [], 422);
            }
            $version = (int) RecetaHamaca::where('hamaca_id', $target->id)->lockForUpdate()->max('version') + 1;
            $recipe = RecetaHamaca::create(['hamaca_id' => $target->id, 'version' => $version, 'estado' => 'borrador', 'observaciones' => $active?->observaciones, 'usuario_id' => $usuario->id]);
            if ($active) {
                $recipe->detallesMateriales()->createMany($active->detallesMateriales->map(fn ($d) => ['material_id' => $d->material_id, 'cantidad' => $d->cantidad, 'porcentaje_merma' => $d->porcentaje_merma])->all());
                $recipe->detallesManoObra()->createMany($active->detallesManoObra->map(fn ($d) => ['proceso_produccion_id' => $d->proceso_produccion_id, 'costo_unitario' => $d->costo_unitario, 'orden' => $d->orden])->all());
            }
            return $recipe->load(['hamaca.categoria', 'hamaca.tamano', 'hamaca.colores', 'detallesMateriales.material', 'detallesManoObra.proceso']);
        });
    }

    public function updateDraft(RecetaHamaca $recipe, array $data): RecetaHamaca
    {
        if ($recipe->estado !== 'borrador') throw new BusinessRuleException('Solo se puede modificar una receta en borrador.', [], 422);
        return DB::transaction(function () use ($recipe, $data) {
            $recipe = RecetaHamaca::query()->lockForUpdate()->findOrFail($recipe->id);
            if ($recipe->estado !== 'borrador') throw new BusinessRuleException('Solo se puede modificar una receta en borrador.', [], 422);
            if (array_key_exists('observaciones', $data)) $recipe->update(['observaciones' => $data['observaciones']]);
            $recipe->detallesMateriales()->delete(); $recipe->detallesManoObra()->delete();
            $recipe->detallesMateriales()->createMany($data['materiales']); $recipe->detallesManoObra()->createMany($data['mano_obra']);
            return $recipe->load(['hamaca.categoria', 'hamaca.tamano', 'hamaca.colores', 'detallesMateriales.material', 'detallesManoObra.proceso']);
        });
    }

    public function activate(RecetaHamaca $recipe, Usuario $usuario): RecetaHamaca
    {
        return DB::transaction(function () use ($recipe, $usuario) {
            $recipe = RecetaHamaca::query()->lockForUpdate()->findOrFail($recipe->id);
            if ($recipe->estado !== 'borrador') throw new BusinessRuleException('Solo se puede activar una receta en borrador.', [], 422);
            $recipe->load(['detallesMateriales', 'detallesManoObra']);
            if ($recipe->detallesMateriales->isEmpty() && $recipe->detallesManoObra->isEmpty()) throw new BusinessRuleException('La receta debe contener materiales o mano de obra.', [], 422);
            $materials = $recipe->detallesMateriales->pluck('material_id'); $processes = $recipe->detallesManoObra->pluck('proceso_produccion_id');
            if ($materials->isNotEmpty() && DB::table('materiales')->whereIn('id', $materials)->where('state', true)->count() !== $materials->count()) throw new BusinessRuleException('La receta contiene materiales inactivos.');
            if ($processes->isNotEmpty() && DB::table('procesos_produccion')->whereIn('id', $processes)->where('state', true)->count() !== $processes->count()) throw new BusinessRuleException('La receta contiene procesos inactivos.');
            RecetaHamaca::where('hamaca_id', $recipe->hamaca_id)->where('estado', 'activa')->update(['estado' => 'archivada']);
            $recipe->update(['estado' => 'activa', 'activado_por_id' => $usuario->id, 'activated_at' => now()]);
            return $recipe->load(['hamaca.categoria', 'hamaca.tamano', 'hamaca.colores', 'detallesMateriales.material', 'detallesManoObra.proceso']);
        });
    }

    public function discard(RecetaHamaca $recipe): RecetaHamaca
    {
        if ($recipe->estado !== 'borrador') throw new BusinessRuleException('Solo se puede descartar una receta en borrador.', [], 422);
        $recipe->update(['estado' => 'descartada']); return $recipe->load(['hamaca.categoria', 'hamaca.tamano', 'hamaca.colores', 'detallesMateriales.material', 'detallesManoObra.proceso']);
    }
}
