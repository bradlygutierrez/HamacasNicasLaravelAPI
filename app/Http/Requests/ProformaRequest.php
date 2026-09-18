<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProformaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'nombre_cliente' => 'required_without:cliente_id|string|max:150',
            'ruc' => 'nullable|string|max:50', 'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50', 'correo' => 'nullable|email|max:150',
            'vendedor_id' => 'nullable|integer|exists:usuarios,id',
            'fecha' => 'nullable|date', 'valida_hasta' => 'nullable|date', 'observaciones' => 'nullable|string',
            'descuento' => 'nullable|numeric|min:0', 'descuento_global' => 'nullable|numeric|min:0', 'aplica_iva' => 'nullable|boolean', 'tasa_iva' => 'nullable|numeric|between:0,100',
            'aplica_ir' => 'nullable|boolean', 'tasa_ir' => 'nullable|numeric|between:0,100', 'tasa_comision_vendedor' => 'nullable|numeric|between:0,100',
            'detalles' => 'required|array|min:1',
            'detalles.*.hamaca_id' => 'required|integer|exists:hamacas,id',
            'detalles.*.hamaca_variante_id' => 'nullable|integer|exists:hamaca_variantes,id',
            'detalles.*.cantidad' => 'required|integer|min:1', 'detalles.*.precio_unitario' => 'nullable|numeric|min:0', 'detalles.*.descuento' => 'nullable|numeric|min:0',
            'detalles.*.servicios' => 'nullable|array',
            'detalles.*.servicios.*.servicio_adicional_id' => 'required|integer|exists:servicios_adicionales,id',
            'detalles.*.servicios.*.cantidad' => 'required|numeric|gt:0', 'detalles.*.servicios.*.detalle' => 'nullable|string', 'detalles.*.servicios.*.precio_unitario' => 'nullable|numeric|min:0', 'detalles.*.servicios.*.descuento' => 'nullable|numeric|min:0',
            'servicios_pedido' => 'nullable|array',
            'servicios_pedido.*.servicio_adicional_id' => 'required|integer|exists:servicios_adicionales,id',
            'servicios_pedido.*.cantidad' => 'required|numeric|gt:0', 'servicios_pedido.*.detalle' => 'nullable|string', 'servicios_pedido.*.precio_unitario' => 'nullable|numeric|min:0', 'servicios_pedido.*.descuento' => 'nullable|numeric|min:0', 'servicios_pedido.*.costo_base_unitario_override' => 'nullable|numeric|min:0',
        ];
    }
}
