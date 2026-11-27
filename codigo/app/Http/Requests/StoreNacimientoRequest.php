<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNacimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'apellido_nombre' => ['required', 'string', 'max:200'],
            'dni' => ['required', 'string', 'max:20'],
            'fecha_nacimiento' => ['required', 'date', 'before_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ], GuardarFiliacionRequest::reglas());
    }
}
