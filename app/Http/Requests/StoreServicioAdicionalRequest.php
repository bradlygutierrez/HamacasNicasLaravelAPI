<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServicioAdicionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['nullable', 'string', 'max:50', 'unique:servicios_adicionales,codigo'],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'alcance' => ['required', Rule::in(['producto', 'pedido'])],
            'metodo_calculo' => ['required', Rule::in(['fijo', 'por_producto', 'por_unidad', 'por_caracter', 'manual'])],
            'unidad' => ['nullable', 'string', 'max:50'],
            'precio_venta_actual' => ['required', 'numeric', 'min:0'],
            'costo_actual' => ['required', 'numeric', 'min:0'],
        ];
    }
}
