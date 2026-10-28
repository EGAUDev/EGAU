<?php
namespace App\Console\Commands;

use App\Services\LecturaCore;
use App\Services\SincronizadorCore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migración v2.2 · parte 7: las pantallas ya leen de v2.2. Este comando pasa
 * los tutores que ya existen y compara, registro por registro, lo que hay en
 * las tablas viejas contra lo que las pantallas leen ahora de v2.2.
 * Desde la parte 9 las pantallas usan los ids de v2.2: los ids viejos se
 * traducen con migracion.mapa antes de comparar.
 * Se puede correr cuantas veces se quiera.
 */
class RevisarLecturas extends Command
{
    protected $signature = 'migracion:lecturas';
    protected $description = 'Pasa los tutores a v2.2 y compara las tablas viejas contra lo que se lee de v2.2';

    // Columna de id -> tabla vieja en migracion.mapa (v2.2 tiene sus propios ids)
    private const IDS = [
        'id_sede' => 'sede', 'id_rol' => 'rol', 'id_tutor' => 'tutor', 'id_alumno' => 'alumno',
        'id_profesor' => 'profesor', 'id_personal' => 'personal', 'id_curso' => 'curso', 'id_grupo' => 'grupo',
        'id_inscripcion' => 'inscripcion', 'id_extraescolar' => 'extraescolar',
        'id_inscripcionextra' => 'inscripcionextra', 'id_factura' => 'factura',
    ];

    // Diferencias que ya se saben y no cuentan: v2.2 guarda el dato de otra forma
    private const IGNORAR = ['fecha_modificacion', 'fecha_registro', 'fecha_limite', 'fecha_asignacion', 'fecha_baja', 'motivo_baja', 'cupo_actual'];

    public function handle(): int
    {
        if (!DB::table('migracion.mapa')->where('tabla_origen', 'inscripcion')->exists()) {
            $this->error('Corre primero php artisan migracion:academico en esta base.');
            return self::FAILURE;
        }

        DB::transaction(function () {
            foreach (DB::table('alumno')->whereNotNull('id_tutor')->orderBy('id_alumno')->pluck('id_alumno') as $id) {
                SincronizadorCore::tutorDeAlumno((int) $id);
            }
        });

        $tablas = [
            'sede'                    => [LecturaCore::sedes(), 'id_sede'],
            'rol'                     => [LecturaCore::roles(), 'id_rol'],
            'alumno'                  => [LecturaCore::personas('alumno'), 'id_alumno'],
            'profesor'                => [LecturaCore::personas('profesor'), 'id_profesor'],
            'personal'                => [LecturaCore::personas('personal'), 'id_personal'],
            'tutor'                   => [LecturaCore::tutores(), 'id_tutor'],
            'curso'                   => [LecturaCore::cursos(), 'id_curso'],
            'grupo'                   => [LecturaCore::grupos(), 'id_grupo'],
            'inscripcion'             => [LecturaCore::inscripciones(), 'id_inscripcion'],
            'extraescolar'            => [LecturaCore::extraescolares(), 'id_extraescolar'],
            'inscripcionextraescolar' => [LecturaCore::inscripcionesExtra(), 'id_inscripcionextra'],
            'factura'                 => [LecturaCore::facturas(), 'id_factura'],
        ];

        $mapa = DB::table('migracion.mapa')->get()->groupBy('tabla_origen')
            ->map(fn ($f) => $f->pluck('id_nuevo', 'id_origen')->all());
        $nuevoId = function (string $col, $valor) use ($mapa) {
            if ($valor === null || !isset(self::IDS[$col])) {
                return $valor;
            }
            return $mapa[self::IDS[$col]][$valor] ?? "{$valor} (sin migrar)";
        };

        $filas = [];
        $detalle = [];
        foreach ($tablas as $tabla => [$sub, $pk]) {
            $nuevo = DB::query()->fromSub($sub, 'x')->get()->keyBy($pk);
            $viejo = DB::table($tabla)->get()
                ->map(function ($v) use ($nuevoId) {
                    foreach ((array) $v as $col => $valor) {
                        $v->$col = $nuevoId($col, $valor);
                    }
                    return $v;
                })
                ->keyBy($pk);
            $iguales = 0;
            foreach ($viejo as $id => $v) {
                $n = $nuevo[$id] ?? null;
                if (!$n) {
                    $detalle[] = "{$tabla} {$id}: no está en v2.2";
                    continue;
                }
                $difs = [];
                foreach ((array) $n as $col => $valor) {
                    if (in_array($col, self::IGNORAR, true) || !property_exists($v, $col)) {
                        continue;
                    }
                    if (self::normal($v->$col, $col) !== self::normal($valor, $col)) {
                        $difs[] = "{$col}: «{$v->$col}» → «{$valor}»";
                    }
                }
                if ($difs) {
                    $detalle[] = "{$tabla} {$id}: " . implode(', ', $difs);
                } else {
                    $iguales++;
                }
            }
            $filas[] = [$tabla, $viejo->count(), $nuevo->count(), $iguales];
        }

        // Horarios: v2.2 no guarda repetidos ni los que empalman al profesor
        $llave = "id_grupo || ' ' || dia_semana || ' ' || to_char(hora_inicio, 'HH24:MI') || '-' || to_char(hora_fin, 'HH24:MI')";
        $h1 = DB::table('horario')->selectRaw("DISTINCT id_grupo, {$llave} AS k")->get()
            ->map(fn ($h) => preg_replace('/^\d+/', (string) $nuevoId('id_grupo', $h->id_grupo), $h->k))->all();
        $h2 = DB::query()->fromSub(LecturaCore::horarios(), 'horario')->selectRaw("{$llave} AS k")->pluck('k')->all();
        foreach (array_diff($h1, $h2) as $k) {
            $detalle[] = "horario del grupo {$k}: no está en v2.2 (choca con otra clase del mismo profesor)";
        }
        $filas[] = ['horario (sin repetidos)', count($h1), count($h2), count(array_intersect($h1, $h2))];

        $this->table(['Tabla', 'En v1', 'Se leen de v2.2', 'Iguales'], $filas);
        if ($detalle) {
            $this->line('Diferencias:');
            foreach ($detalle as $d) {
                $this->line('  ' . $d);
            }
        }
        $this->info('Listo.');
        return self::SUCCESS;
    }

    private static function normal($valor, string $col): string
    {
        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }
        if ($valor === null || $valor === '') {
            return '';
        }
        if (in_array((string) $valor, ['t', 'f'], true) && $col !== 'genero') {
            return $valor === 't' ? '1' : '0';
        }
        if ($col === 'email') {
            return mb_strtolower((string) $valor);
        }
        if (is_numeric($valor)) {
            return (string) (0 + $valor);
        }
        return trim((string) $valor);
    }
}
