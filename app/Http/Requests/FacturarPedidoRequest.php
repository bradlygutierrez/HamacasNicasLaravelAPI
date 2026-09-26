<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FacturarPedidoRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'metodo_pago' => ['nullable', 'string', 'max:50'],
            'canal' => ['required', 'in:pos,ecommerce'],
            'ubicacion_id' => ['required', 'integer', 'exists:ubicaciones,id'],
            'usuario_inventario_id' => ['nullable', 'integer', 'exists:usuarios,id'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.pedido_detalle_id' => ['required', 'integer', 'distinct', 'exists:pedido_detalles,id'],
        ];
    }
}
