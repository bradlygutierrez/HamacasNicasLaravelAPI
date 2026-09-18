<?php

namespace App\Http\Requests;

use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $material = $this->route('material');
        $materialId = $material instanceof Material ? $material->id : $material;

        return [
            'codigo' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('materiales', 'codigo')->ignore($materialId)],
            'nombre' => ['sometimes', 'required', 'string', 'max:150'],
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'unidad_consumo' => ['sometimes', 'required', 'string', 'max:50'],
            'unidad_compra' => ['sometimes', 'required', 'string', 'max:50'],
            'contenido_por_compra' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'precio_actual' => ['sometimes', 'required', 'numeric', 'min:0'],
            'porcentaje_merma' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $material = $this->route('material');
            $consumo = $this->input('unidad_consumo', $material instanceof Material ? $material->unidad_consumo : null);
            $compra = $this->input('unidad_compra', $material instanceof Material ? $material->unidad_compra : null);
            $contenido = $this->input('contenido_por_compra', $material instanceof Material ? $material->contenido_por_compra : null);

            if ($consumo !== $compra && blank($contenido)) {
                $validator->errors()->add(
                    'contenido_por_compra',
                    'El contenido por compra es obligatorio cuando las unidades son diferentes.'
                );
            }
        });
    }
}
