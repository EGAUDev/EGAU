<?php
namespace App\Console\Commands;

use App\Services\SincronizadorCore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migración v2.2 · parte 4: pasa a academico.* los cursos, grupos (con
 * horarios) e inscripciones que ya existen en public, y liga los cargos de
 * pagos a su inscripción. Usa la misma lógica que la app al guardar, así que
 * se puede correr varias veces: solo agrega lo que falte y re-sincroniza lo demás.
 * Requiere que ya estén corridas las partes 1 y 3 (01 y 02 .sql).
 */
class MigrarAcademico extends Command
{
    protected $signature = 'migracion:academico';
    protected $description = 'Copia cursos, grupos, horarios e inscripciones de public al esquema académico de v2.2';

    public function handle(): int
    {
        if (!DB::table('migracion.mapa')->where('tabla_origen', 'alumno')->exists()) {
            $this->error('Corre primero 01-migracion-auth.sql en esta base.');
            return self::FAILURE;
        }

        DB::transaction(function () {
            foreach (DB::table('curso')->orderBy('id_curso')->pluck('id_curso') as $id) {
                SincronizadorCore::curso((int) $id);
            }
            foreach (DB::table('grupo')->orderBy('id_grupo')->pluck('id_grupo') as $id) {
                SincronizadorCore::grupo((int) $id);
            }
            foreach (DB::table('inscripcion')->orderBy('id_inscripcion')->pluck('id_inscripcion') as $id) {
                SincronizadorCore::inscripcion((int) $id);
            }
        });

        $cuenta = fn (string $tabla) => DB::table('migracion.mapa')->where('tabla_origen', $tabla)->count();
        $this->table(['Qué', 'En v1', 'En v2.2'], [
            ['Cursos', DB::table('curso')->count(), $cuenta('curso')],
            ['Grupos', DB::table('grupo')->count(), $cuenta('grupo')],
            ['Horarios (sin repetidos)', DB::table('horario')->distinct()->count(DB::raw('(id_grupo, dia_semana, hora_inicio, hora_fin)')), DB::table('academico.horario')->count()],
            ['Inscripciones', DB::table('inscripcion')->count(), $cuenta('inscripcion')],
            ['  activas', DB::table('inscripcion')->where('estatus', true)->count(), DB::table('academico.inscripcion')->where('estado', 'activa')->count()],
            ['  en lista de espera (cupo lleno en v2.2)', '-', DB::table('academico.inscripcion')->where('estado', 'lista_espera')->count()],
            ['Cargos de grupo ligados a su inscripción', DB::table('factura')->whereNotNull('id_inscripcion')->count(), DB::table('finanzas.cargo')->whereNotNull('id_inscripcion')->count()],
        ]);
        $this->info('Listo.');
        return self::SUCCESS;
    }
}
