<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private function sobrantes(): array
    {
        return [
            'acta_nacimientos' => ['nombres','apellido_paterno','apellido_materno','sexo','hora_nacimiento','localidad','lugar_ocurrencia','lugar_nacimiento','nombre_padre','dni_padre','nombre_madre','dni_madre','padre_prenombres','padre_apellido1','padre_apellido2','padre_nacionalidad','padre_dni','madre_prenombres','madre_apellido1','madre_apellido2','madre_nacionalidad','madre_dni','madre_domicilio','declarante1_nombre','declarante1_vinculo','declarante1_dni','declarante2_nombre','declarante2_vinculo','declarante2_dni'],
            'acta_defunciones' => ['nombres','apellido_paterno','apellido_materno','prenombres','apellido1','apellido2','nacionalidad','lugar_nacimiento','fecha_nacimiento','hora_defuncion','localidad','lugar_ocurrencia','lugar_defuncion','domicilio','padre_nombre','madre_nombre','declarante_nombre','declarante_vinculo','declarante_dni','medico_certifica'],
            'acta_matrimonios' => ['lugar_celebracion','expediente','c1_nombres','c1_apellido_paterno','c1_apellido_materno','c1_fecha_nacimiento','c1_prenombres','c1_apellido1','c1_apellido2','c1_dni','c1_edad','c1_estado_civil','c1_nacionalidad','c1_lugar_nacimiento','c2_nombres','c2_apellido_paterno','c2_apellido_materno','c2_fecha_nacimiento','c2_prenombres','c2_apellido1','c2_apellido2','c2_dni','c2_edad','c2_estado_civil','c2_nacionalidad','c2_lugar_nacimiento'],
        ];
    }

    public function up(): void
    {
        $respaldo = [];
        foreach ($this->sobrantes() as $tabla => $columnas) {
            $respaldo[$tabla] = ['columnas'=>Schema::getColumns($tabla), 'indices'=>Schema::getIndexes($tabla), 'registros'=>DB::table($tabla)->orderBy('id')->get()->all()];
        }
        $ruta = 'private/schema-backups/detalles-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
        $json = json_encode($respaldo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (!Storage::disk('local')->put($ruta, $json) || Storage::disk('local')->get($ruta) !== $json) {
            throw new \RuntimeException('No se pudo verificar el respaldo. No se eliminaron columnas.');
        }
        foreach ($this->sobrantes() as $tabla => $columnas) {
            $existentes = array_values(array_intersect($columnas, Schema::getColumnListing($tabla)));
            if (!$existentes) { continue; }
            foreach (Schema::getIndexes($tabla) as $indice) {
                if (!empty(array_intersect($indice['columns'], $existentes)) && !$indice['primary']) {
                    Schema::table($tabla, fn (Blueprint $t) => $t->dropIndex($indice['name']));
                }
            }
            Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn($existentes));
        }
        foreach ($respaldo as $tabla => $datos) {
            if (DB::table($tabla)->count() !== count($datos['registros'])) {
                throw new \RuntimeException('La cantidad de registros cambió durante la limpieza. Respaldo: '.$ruta);
            }
            foreach ($datos['registros'] as $original) {
                $actual = DB::table($tabla)->where('id', $original->id)->first();
                foreach ((array) $actual as $campo => $valor) {
                    if ($valor !== $original->$campo) { throw new \RuntimeException('Un dato conservado cambió. Respaldo: '.$ruta); }
                }
            }
        }
    }

    public function down(): void
    {
        throw new \RuntimeException('Esta limpieza requiere restaurar las columnas y datos desde private/schema-backups; no se revierte creando columnas vacías.');
    }
};
