<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventarioEntradaRequest extends FormRequest
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
            'hamaca_id' => ['required', 'integer', 'exists:hamacas,id'],
            'usuario_id' => ['required', 'integer', 'exists:usuarios,id'],
            'ubicacion_id' => ['required', 'integer', 'exists:ubicaciones,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'fecha' => ['sometimes', 'date'],
        ];
    }
}
