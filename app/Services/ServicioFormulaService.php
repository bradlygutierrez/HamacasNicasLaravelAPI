<?php

namespace App\Services;

use App\Models\ServicioAdicional;
use Illuminate\Support\Facades\DB;

class ServicioFormulaService
{
    public function update(ServicioAdicional $service, array $data): ServicioAdicional
    {
        return DB::transaction(function () use ($service, $data): ServicioAdicional {
            $service = ServicioAdicional::query()->lockForUpdate()->findOrFail($service->id);
            $service->materiales()->delete();
            $service->manoObra()->delete();
            $service->materiales()->createMany($data['materiales']);
            $service->manoObra()->createMany($data['mano_obra']);

            return $service->load(['materiales.material', 'manoObra.proceso']);
        });
    }
}
