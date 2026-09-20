<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class PedidoStatusRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['estado' => 'required|in:materiales_pendientes,materiales_listos,en_produccion,terminado,cancelado', 'comentario' => 'nullable|string']; } }
