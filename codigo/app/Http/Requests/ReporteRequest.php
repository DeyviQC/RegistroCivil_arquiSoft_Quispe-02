<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReporteRequest extends FormRequest
{
    public const REPORTES = [
        'sobres' => ['titulo'=>'Reporte de sobres','descripcion'=>'Inventario por tipo y sobre: actas, PDF y espacios libres. Los sobres nuevos admiten 25 actas; los históricos conservan su agrupación original.','filtros'=>['tipo']],
        'actas' => ['titulo'=>'Listado detallado de actas','descripcion'=>'Consulta registros individuales y su ubicación física.','filtros'=>['tipo','fecha_desde','fecha_hasta','usuario_id']],
        'resumen' => ['titulo'=>'Resumen mensual','descripcion'=>'Totales por tipo de acta del mes y año de inscripción seleccionados.','filtros'=>['anio','mes','tipo','usuario_id']],
        'anual' => ['titulo'=>'Resumen anual','descripcion'=>'Totales por tipo de acta para un año completo de inscripción.','filtros'=>['anio','tipo','usuario_id']],
        'pendientes' => ['titulo'=>'Pendientes de PDF','descripcion'=>'Identifica registros sin documento adjunto o cuyo archivo ya no está disponible.','filtros'=>['tipo','fecha_desde','fecha_hasta','usuario_id']],
        'actividad' => ['titulo'=>'Actividad de usuarios','descripcion'=>'Acciones registradas en auditoría. Las fechas corresponden a la hora de Lima.','filtros'=>['fecha_desde','fecha_hasta','usuario_id']],
    ];

    public function authorize(): bool { return $this->user()?->can('viewAny', \App\Models\Acta::class) ?? false; }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('reporte')) { $this->merge(['reporte'=>'actas']); }
    }

    public function rules(): array
    {
        $reporte = is_string($this->input('reporte')) ? $this->input('reporte') : '';
        $permitidos = self::REPORTES[$reporte]['filtros'] ?? [];
        $resumen = in_array($reporte, ['resumen','anual']);
        $rules = [
            'reporte'=>['required',Rule::in(array_keys(self::REPORTES))],
            'mes'=>[$reporte==='resumen'?'required':'prohibited','integer','min:1','max:12'],
            'tipo'=>['nullable',Rule::in(['NACIMIENTO','DEFUNCION','MATRIMONIO'])],
            'anio'=>[$resumen?'required':'nullable','integer','min:1900','max:'.now('America/Lima')->year],
            'sobre'=>['nullable','integer','min:1','max:999999'],
            'correlativo'=>['nullable','integer','min:1','max:25'],
            'fecha_desde'=>['nullable','date_format:Y-m-d','before_or_equal:today'],
            'fecha_hasta'=>['nullable','date_format:Y-m-d','before_or_equal:today'],
            'usuario_id'=>['nullable','integer','exists:users,id'],
            'page'=>['nullable','integer','min:1'],
        ];
        if ($this->filled('fecha_desde')) { $rules['fecha_hasta'][]='after_or_equal:fecha_desde'; }
        foreach (['tipo','anio','mes','sobre','correlativo','fecha_desde','fecha_hasta','usuario_id'] as $campo) {
            if (! in_array($campo,$permitidos)) { $rules[$campo]=['prohibited']; }
        }
        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->filled('sobre') && !$this->filled('tipo')) { $v->errors()->add('tipo','Seleccione un tipo de acta para filtrar por sobre.'); }
            if ($this->filled('correlativo') && !$this->filled('sobre')) { $v->errors()->add('sobre','Indique el sobre para filtrar por correlativo.'); }
            if ($this->filled('anio') && ($this->filled('fecha_desde') || $this->filled('fecha_hasta'))) { $v->errors()->add('anio','Use un año o un rango de fechas, no ambos.'); }
        });
    }

    public function messages(): array
    {
        return ['*.prohibited'=>'Este filtro no corresponde al reporte seleccionado.','anio.required'=>'Seleccione el año del resumen.','fecha_hasta.after_or_equal'=>'La fecha final debe ser igual o posterior a la inicial.'];
    }
}
