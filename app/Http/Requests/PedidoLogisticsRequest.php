<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class PedidoLogisticsRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['fecha_entrega_estimada' => 'nullable|date', 'observaciones_internas' => 'nullable|string']; } }
