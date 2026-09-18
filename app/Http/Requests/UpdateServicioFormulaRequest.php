<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServicioFormulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'materiales' => ['present', 'array'],
            'materiales.*.material_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('materiales', 'id')->where(fn ($query) => $query->where('state', true)),
            ],
            'materiales.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'materiales.*.porcentaje_merma' => ['nullable', 'numeric', 'between:0,100'],
            'mano_obra' => ['present', 'array'],
            'mano_obra.*.proceso_produccion_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('procesos_produccion', 'id')->where(fn ($query) => $query->where('state', true)),
            ],
            'mano_obra.*.costo_unitario' => ['required', 'numeric', 'min:0'],
            'mano_obra.*.orden' => ['nullable', 'integer'],
        ];
    }
}
