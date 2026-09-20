<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class PedidoStatusRequest extends FormRequest { public function authorize(): bool { return true; } protected function prepareForValidation(): void { if ($this->has('comentario') && is_string($this->input('comentario'))) $this->merge(['comentario' => trim($this->input('comentario'))]); } public function rules(): array { return ['estado' => 'required|in:materiales_pendientes,materiales_listos,en_produccion,terminado,cancelado', 'comentario' => 'required_if:estado,cancelado|nullable|string']; } }
