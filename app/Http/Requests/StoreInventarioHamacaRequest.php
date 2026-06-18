<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventarioHamacaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hamaca_id' => 'required|integer|exists:hamacas,id',
            'usuario_id' => 'required|integer|exists:usuarios,id',
            'ubicacion_id' => 'required|integer|exists:ubicaciones,id',
            'color_ids' => 'required|array|min:1',
            'color_ids.*' => 'integer|exists:colores,id',
            'cantidad' => 'required|integer|min:1',
        ];
    }
}
