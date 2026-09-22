<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\HamacaVariante;
use App\Models\RecetaHamaca;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class RecetaHamacaService
{
    public function crearBorrador(HamacaVariante $variante, Usuario $usuario, ?int $sourceVariantId = null): RecetaHamaca
    {
        return DB::transaction(function () use ($variante, $usuario, $sourceVariantId): RecetaHamaca {
            $lockedVariant = HamacaVariante::query()->lockForUpdate()->findOrFail($variante->id);

            if (RecetaHamaca::where('hamaca_variante_id', $lockedVariant->id)->where('estado', 'borrador')->exists()) {
                throw new BusinessRuleException('La variante ya tiene un borrador de receta.');
            }

            $source = $lockedVariant;
            if ($sourceVariantId !== null) {
                $source = HamacaVariante::query()
                    ->where('hamaca_id', $lockedVariant->hamaca_id)
                    ->where('state', true)
                    ->find($sourceVariantId);
                if (!$source) {
                    throw new BusinessRuleException('La variante origen debe pertenecer al mismo modelo.', [], 422);
                }
            }

            $active = RecetaHamaca::with(['detallesMateriales', 'detallesManoObra'])
                ->where('hamaca_variante_id', $source->id)
                ->where('estado', 'activa')
                ->first();
            if ($sourceVariantId !== null && !$active) {
                throw new BusinessRuleException('La variante origen no tiene una fórmula activa.', [], 422);
            }
            $version = ((int) RecetaHamaca::where('hamaca_variante_id', $lockedVariant->id)->lockForUpdate()->max('version')) + 1;

            $recipe = RecetaHamaca::create([
                'hamaca_id' => $lockedVariant->hamaca_id,
                'hamaca_variante_id' => $lockedVariant->id,
                'version' => $version,
                'estado' => 'borrador',
                'observaciones' => $active?->observaciones,
                'usuario_id' => $usuario->id,
            ]);

            if ($active) {
                $recipe->detallesMateriales()->createMany($active->detallesMateriales->map(fn ($detail) => [
                    'material_id' => $detail->material_id,
                    'cantidad' => $detail->cantidad,
                    'porcentaje_merma' => $detail->porcentaje_merma,
                ])->all());
                $recipe->detallesManoObra()->createMany($active->detallesManoObra->map(fn ($detail) => [
                    'proceso_produccion_id' => $detail->proceso_produccion_id,
                    'costo_unitario' => $detail->costo_unitario,
                    'orden' => $detail->orden,
                ])->all());
            }

            return $recipe->load(['detallesMateriales.material', 'detallesManoObra.proceso']);
        });
    }

    public function updateDraft(RecetaHamaca $recipe, array $data): RecetaHamaca
    {
        if ($recipe->estado !== 'borrador') {
            throw new BusinessRuleException('Solo se puede modificar una receta en borrador.');
        }

        return DB::transaction(function () use ($recipe, $data): RecetaHamaca {
            $recipe = RecetaHamaca::query()->lockForUpdate()->findOrFail($recipe->id);
            if ($recipe->estado !== 'borrador') {
                throw new BusinessRuleException('Solo se puede modificar una receta en borrador.');
            }

            if (array_key_exists('observaciones', $data)) {
                $recipe->update(['observaciones' => $data['observaciones']]);
            }
            $recipe->detallesMateriales()->delete();
            $recipe->detallesManoObra()->delete();
            $recipe->detallesMateriales()->createMany($data['materiales']);
            $recipe->detallesManoObra()->createMany($data['mano_obra']);

            return $recipe->load(['detallesMateriales.material', 'detallesManoObra.proceso']);
        });
    }

    public function activate(RecetaHamaca $recipe, Usuario $usuario): RecetaHamaca
    {
        return DB::transaction(function () use ($recipe, $usuario): RecetaHamaca {
            $recipe = RecetaHamaca::query()->lockForUpdate()->findOrFail($recipe->id);
            if ($recipe->hamaca_variante_id) {
                HamacaVariante::query()->lockForUpdate()->findOrFail($recipe->hamaca_variante_id);
            }
            if ($recipe->estado !== 'borrador') {
                throw new BusinessRuleException('Solo se puede activar una receta en borrador.');
            }

            $recipe->load(['detallesMateriales', 'detallesManoObra']);
            if ($recipe->detallesMateriales->isEmpty() && $recipe->detallesManoObra->isEmpty()) {
                throw new BusinessRuleException('La receta debe contener materiales o mano de obra.');
            }

            $activeMaterialIds = $recipe->detallesMateriales->pluck('material_id');
            $activeProcessIds = $recipe->detallesManoObra->pluck('proceso_produccion_id');
            if ($activeMaterialIds->isNotEmpty() && DB::table('materiales')->whereIn('id', $activeMaterialIds)->where('state', true)->count() !== $activeMaterialIds->count()) {
                throw new BusinessRuleException('La receta contiene materiales inactivos.');
            }
            if ($activeProcessIds->isNotEmpty() && DB::table('procesos_produccion')->whereIn('id', $activeProcessIds)->where('state', true)->count() !== $activeProcessIds->count()) {
                throw new BusinessRuleException('La receta contiene procesos inactivos.');
            }

            $activeQuery = RecetaHamaca::where('hamaca_id', $recipe->hamaca_id)
                ->where('estado', 'activa')
                ->when($recipe->hamaca_variante_id, fn ($query) => $query->where('hamaca_variante_id', $recipe->hamaca_variante_id), fn ($query) => $query->whereNull('hamaca_variante_id'));
            $activeQuery->update(['estado' => 'archivada']);

            $recipe->update([
                'estado' => 'activa',
                'activado_por_id' => $usuario->id,
                'activated_at' => now(),
            ]);

            return $recipe->load(['detallesMateriales.material', 'detallesManoObra.proceso']);
        });
    }

    public function discard(RecetaHamaca $recipe): RecetaHamaca
    {
        return DB::transaction(function () use ($recipe): RecetaHamaca {
            $recipe = RecetaHamaca::query()->lockForUpdate()->findOrFail($recipe->id);
            if ($recipe->estado !== 'borrador') {
                throw new BusinessRuleException('Solo se puede descartar una receta en borrador.');
            }

            $recipe->update(['estado' => 'descartada']);

            return $recipe->load(['detallesMateriales.material', 'detallesManoObra.proceso']);
        });
    }
}
