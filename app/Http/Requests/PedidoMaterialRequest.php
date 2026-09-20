<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class PedidoMaterialRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['estado' => 'sometimes|in:pendiente,parcial,listo', 'cantidad_compra_real' => 'sometimes|numeric|min:0', 'costo_compra_real' => 'sometimes|numeric|min:0', 'observaciones' => 'nullable|string']; } }
