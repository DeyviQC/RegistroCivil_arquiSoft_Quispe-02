<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarFiliacionRequest;
use App\Models\Acta;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiliacionController extends Controller
{
    public function edit(Acta $acta)
    {
        $this->authorize('create', Acta::class);
        abort_unless($acta->tipo === 'NACIMIENTO' && $acta->nacimiento, 404);
        return view('actas.filiacion', ['acta' => $acta, 'nacimiento' => $acta->nacimiento]);
    }
    public function update(GuardarFiliacionRequest $request, Acta $acta, AuditoriaService $auditoria)
    {
        $this->authorize('create', Acta::class);
        abort_unless($acta->tipo === 'NACIMIENTO', 404);
        DB::transaction(function () use ($request, $acta, $auditoria) {
            $nacimiento = $acta->nacimiento()->lockForUpdate()->firstOrFail();
            if ($nacimiento->filiacion_version !== $request->integer('filiacion_version')) throw ValidationException::withMessages(['filiacion_version' => 'Los datos de padres cambiaron. Recarga la ficha antes de guardar.']);
            $campos = \App\Models\ActaNacimiento::CAMPOS_FILIACION;
            $antes = $nacimiento->only($campos);
            $datos = $request->safe()->except('filiacion_version');
            foreach (['madre_nombre', 'madre_dni', 'padre_nombre', 'padre_dni'] as $campo) if ($datos[$campo.'_estado'] !== 'REGISTRADO') $datos[$campo] = null;
            $nacimiento->fill($datos);
            if (!$nacimiento->isDirty($campos)) return;
            $nacimiento->filiacion_version++;
            $nacimiento->save();
            DB::table('filiacion_historial')->insert(['acta_nacimiento_id' => $nacimiento->id, 'usuario_id' => $request->user()->id, 'antes' => json_encode($antes, JSON_UNESCAPED_UNICODE), 'despues' => json_encode($nacimiento->only($campos), JSON_UNESCAPED_UNICODE), 'pdf_hash' => $acta->pdf_hash, 'created_at' => now()]);
            $auditoria->registrar('COMPLETAR_FILIACION', 'Registro Civil', $acta->id, 'Datos de madre y padre actualizados desde el acta original.');
        });
        return redirect()->route('actas.show', $acta)->with('exito', 'Datos de madre y padre guardados con historial de cambios.');
    }
}
