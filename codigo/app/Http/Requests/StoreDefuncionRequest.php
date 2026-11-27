<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDefuncionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'apellido_nombre' => ['required', 'string', 'max:200'],
            'dni' => ['required', 'string', 'max:20'],
            'edad' => ['required', 'string', 'max:20'],
            'fecha_defuncion' => ['required', 'date', 'before_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'causa_defuncion' => ['required', 'string', 'max:200'],
        ];
    }
}
