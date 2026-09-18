<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeProformaStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['estado' => 'required|in:enviada,aceptada,rechazada,vencida']; }
}
