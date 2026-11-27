<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarFiliacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && ($this->user()->isAdmin() || $this->user()->isAsistente());
    }
    public function rules(): array { return self::reglas() + ['filiacion_version' => ['required', 'integer', 'min:0']]; }

    public static function reglas(): array
    {
        $reglas = [];
        foreach (['madre_nombre', 'madre_dni', 'padre_nombre', 'padre_dni'] as $campo) {
            $reglas[$campo.'_estado'] = ['required', Rule::in(['REGISTRADO', 'NO_CONSIGNADO', 'DESCONOCIDO', 'ILEGIBLE'])];
            $reglas[$campo] = ['exclude_unless:'.$campo.'_estado,REGISTRADO', 'required', 'string', str_ends_with($campo, '_dni') ? 'regex:/^[0-9]{8}$/' : 'max:200'];
        }
        return $reglas;
    }
}
