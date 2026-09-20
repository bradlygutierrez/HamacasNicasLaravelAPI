<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class PedidoProcesoRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['estado' => 'required|in:pendiente,en_proceso,completado', 'observaciones' => 'nullable|string']; } }
