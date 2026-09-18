<?php

namespace App\Http\Requests;

use App\Models\ServicioAdicional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServicioAdicionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $servicio = $this->route('servicioAdicional');
        $servicioId = $servicio instanceof ServicioAdicional ? $servicio->id : $servicio;

        return [
            'codigo' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('servicios_adicionales', 'codigo')->ignore($servicioId)],
            'nombre' => ['sometimes', 'required', 'string', 'max:150'],
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'alcance' => ['sometimes', Rule::in(['producto', 'pedido'])],
            'metodo_calculo' => ['sometimes', Rule::in(['fijo', 'por_producto', 'por_unidad', 'por_caracter', 'manual'])],
            'unidad' => ['sometimes', 'nullable', 'string', 'max:50'],
            'precio_venta_actual' => ['sometimes', 'required', 'numeric', 'min:0'],
            'costo_actual' => ['sometimes', 'required', 'numeric', 'min:0'],
        ];
    }
}
