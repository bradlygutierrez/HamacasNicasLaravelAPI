<?php

namespace App\Http\Requests;

use App\Models\ProcesoProduccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProcesoProduccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $proceso = $this->route('procesoProduccion');
        $procesoId = $proceso instanceof ProcesoProduccion ? $proceso->id : $proceso;

        return [
            'codigo' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('procesos_produccion', 'codigo')->ignore($procesoId)],
            'nombre' => ['sometimes', 'required', 'string', 'max:150'],
            'descripcion' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
