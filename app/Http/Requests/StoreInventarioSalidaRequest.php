<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventarioSalidaRequest extends FormRequest
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
            'inventario_hamaca_id' => ['required', 'integer', 'exists:inventario_hamacas,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'fecha' => ['sometimes', 'date'],
        ];
    }
}
