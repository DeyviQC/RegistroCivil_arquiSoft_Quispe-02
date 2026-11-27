<?php

namespace App\Http\Requests;

use App\Models\Acta;
use Illuminate\Foundation\Http\FormRequest;


class StoreActaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Acta::class) ?? false;
    }

    public function rules(): array
    {
        $reglas = [
            'tipo' => ['required', 'in:NACIMIENTO,MATRIMONIO,DEFUNCION'],
            'numero_acta' => ['required', 'string', 'max:20'],
            'fecha_acta' => ['required', 'date', 'before_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'pdf' => [
                'required',
                'nullable', 'file', 'mimes:pdf', 'max:' . (int) config('actas.pdf_max_kb', 10240),
            ],
        ];
        $detalle = match ($this->input('tipo')) {
            Acta::TIPO_NACIMIENTO => (new StoreNacimientoRequest())->rules(),
            Acta::TIPO_MATRIMONIO => (new StoreMatrimonioRequest())->rules(),
            Acta::TIPO_DEFUNCION => (new StoreDefuncionRequest())->rules(),
            default => [],
        };
        $permiteExcepcion = in_array($this->input('tipo'), [Acta::TIPO_NACIMIENTO, Acta::TIPO_DEFUNCION]);
        $reglas['dni_no_consignado'] = [$permiteExcepcion ? 'sometimes' : 'prohibited', 'boolean'];
        if ($permiteExcepcion && $this->boolean('dni_no_consignado')) {
            $detalle['dni'] = ['prohibited', 'nullable'];
        }
        return array_merge($reglas, $detalle);
    }

    public function messages(): array
    {
        return [
            'pdf.required' => 'Debe adjuntar el PDF del acta.',
            'pdf.mimes' => 'El archivo debe ser un PDF.',
            'pdf.max' => 'El PDF supera el tamaño máximo permitido.',
            'fecha_acta.before_or_equal' => 'La fecha de registro no puede ser futura.',
            'apellido_nombre.required' => 'Complete los apellidos y nombres.',
            'dni.required' => 'Complete el DNI. En matrimonio, ingrese los DNI de ambos contrayentes.',
            'dni.prohibited' => 'No ingrese un DNI si marcó que el acta no lo consigna.',
            'edad.required' => 'Complete la edad del difunto.',
            'fecha_nacimiento.required' => 'Complete la fecha de nacimiento.',
            'fecha_matrimonio.required' => 'Complete la fecha de celebración.',
            'fecha_defuncion.required' => 'Complete la fecha de fallecimiento.',
            'causa_defuncion.required' => 'Complete la causa de defunción.',
            'correlativo.max' => 'Cada sobre admite un máximo de 25 actas (correlativo 1 a 25).',
        ];
    }
}
