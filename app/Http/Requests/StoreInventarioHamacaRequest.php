<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventarioHamacaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hamaca_variante_id' => 'nullable|integer|exists:hamaca_variantes,id',

            // Compatibilidad temporal con el flujo viejo.
            'hamaca_id' => 'required_without:hamaca_variante_id|integer|exists:hamacas,id',
            'color_ids' => 'required_without:hamaca_variante_id|array|min:1',
            'color_ids.*' => 'integer|exists:colores,id',

            'usuario_id' => 'required|integer|exists:usuarios,id',
            'ubicacion_id' => 'required|integer|exists:ubicaciones,id',
            'cantidad' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'hamaca_variante_id.exists' => 'La variante seleccionada no existe.',
            'hamaca_id.required_without' => 'Selecciona una hamaca o una variante.',
            'color_ids.required_without' => 'Selecciona colores o una variante existente.',
            'usuario_id.required' => 'Selecciona un usuario.',
            'ubicacion_id.required' => 'Selecciona una ubicación.',
            'cantidad.required' => 'Ingresa una cantidad.',
            'cantidad.min' => 'La cantidad debe ser mayor a 0.',
        ];
    }
}