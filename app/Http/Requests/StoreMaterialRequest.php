<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMaterialRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'porcentaje_merma' => $this->input('porcentaje_merma') ?? 0,
            'contenido_por_compra' => $this->input('unidad_consumo') === $this->input('unidad_compra')
                ? 1
                : $this->input('contenido_por_compra'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['nullable', 'string', 'max:50', 'unique:materiales,codigo'],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'unidad_consumo' => ['required', 'string', 'max:50'],
            'unidad_compra' => ['required', 'string', 'max:50'],
            'contenido_por_compra' => ['nullable', 'numeric', 'gt:0'],
            'precio_actual' => ['required', 'numeric', 'min:0'],
            'porcentaje_merma' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $this->input('unidad_consumo') !== $this->input('unidad_compra')
                && blank($this->input('contenido_por_compra'))
            ) {
                $validator->errors()->add(
                    'contenido_por_compra',
                    'El contenido por compra es obligatorio cuando las unidades son diferentes.'
                );
            }
        });
    }
}
