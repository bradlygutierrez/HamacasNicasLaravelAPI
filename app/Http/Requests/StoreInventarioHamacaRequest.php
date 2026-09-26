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
            'hamaca_id' => 'required|integer|exists:hamacas,id',

            'usuario_id' => 'required|integer|exists:usuarios,id',
            'ubicacion_id' => 'required|integer|exists:ubicaciones,id',
            'cantidad' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'hamaca_id.required' => 'Selecciona una hamaca.',
            'usuario_id.required' => 'Selecciona un usuario.',
            'ubicacion_id.required' => 'Selecciona una ubicación.',
            'cantidad.required' => 'Ingresa una cantidad.',
            'cantidad.min' => 'La cantidad debe ser mayor a 0.',
        ];
    }
}
