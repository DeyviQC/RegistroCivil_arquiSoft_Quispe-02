<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Decisión del personal sobre una solicitud; compartida por el panel web y la API REST. */
class DecidirTramiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Las rutas ya restringen el acceso a los roles del personal.
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(['APROBADA', 'OBSERVADA', 'RECHAZADA'])],
            // Solo observar o rechazar exige un motivo escrito.
            'mensaje' => ['nullable', 'required_if:estado,OBSERVADA,RECHAZADA', 'string', 'max:2000'],
            'version' => ['required', 'integer'],
            'vinculo_revisado' => ['nullable', 'boolean'],
            'sustento_revision' => ['nullable', 'string', 'max:1500'],
        ];
    }

    public function messages(): array
    {
        return ['mensaje.required_if' => 'Escribe el motivo para que el ciudadano sepa qué hacer.'];
    }
}