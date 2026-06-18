<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'nombre_cliente' => 'required|string|max:150',
            'ruc' => 'nullable|string|max:50',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'correo' => 'nullable|email|max:150',
            'metodo_pago' => 'nullable|string|max:50',
            'canal' => 'required|in:pos,ecommerce',
            'descuento' => 'nullable|numeric|min:0',
            'aplica_ir' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.inventario_hamaca_id' => 'required|integer|exists:inventario_hamacas,id',
            'items.*.cantidad' => 'required|integer|min:1',
        ];
    }
}
