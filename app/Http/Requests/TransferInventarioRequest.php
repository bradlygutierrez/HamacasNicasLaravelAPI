<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventario_hamaca_id' => 'required|integer|exists:inventario_hamacas,id',
            'cantidad' => 'required|integer|min:1',
            'ubicacion_destino_id' => 'nullable|integer|exists:ubicaciones,id',
        ];
    }
}
