<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AccesoCore;
use App\Services\EscrituraCore;
use App\Services\LecturaCore;

class AlumnoController extends Controller
{
    private function calcularNivel(int $puntaje): array
    {
        return match (true) {
            $puntaje >= 3000 => ['numero' => 6, 'nombre' => 'Rey', 'emoji' => '♚'],
            $puntaje >= 1500 => ['numero' => 5, 'nombre' => 'Reina', 'emoji' => '♛'],
            $puntaje >= 800 => ['numero' => 4, 'nombre' => 'Torre', 'emoji' => '♜'],
            $puntaje >= 400 => ['numero' => 3, 'nombre' => 'Alfil', 'emoji' => '♝'],
            $puntaje >= 150 => ['numero' => 2, 'nombre' => 'Caballo', 'emoji' => '♞'],
            default => ['numero' => 1, 'nombre' => 'Peón', 'emoji' => '♟'],
        };
    }

    private function puntajeSiguienteNivel(int $puntaje): int
    {
        return match (true) {
            $puntaje >= 3000 => 3000,
            $puntaje >= 1500 => 3000,
            $puntaje >= 800 => 1500,
            $puntaje >= 400 => 800,
            $puntaje >= 150 => 400,
            default => 150,
        };
    }

    /** El alumno con sesión abierta, con las columnas de LecturaCore::personas('alumno'). */
    private function alumnoActual(Request $request): ?object
    {
        $idAlumno = AccesoCore::personaActual('alumno', $request);
        return $idAlumno ? DB::query()->fromSub(LecturaCore::personas('alumno'), 'alumno')->where('id_alumno', $idAlumno)->first() : null;
    }

    public function inicio(Request $request)
    {
        $alumno = $this->alumnoActual($request);

        if (!$alumno) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $puntaje = $alumno->puntaje ?? 0;
        $nivel = $this->calcularNivel($puntaje);
        $puntajeSiguiente = $this->puntajeSiguienteNivel($puntaje);

        $horarios = DB::query()->fromSub(LecturaCore::inscripciones(), 'inscripcion')
            ->joinSub(LecturaCore::grupos(), 'grupo', 'inscripcion.id_grupo', '=', 'grupo.id_grupo')
            ->joinSub(LecturaCore::cursos(), 'curso', 'grupo.id_curso', '=', 'curso.id_curso')
            ->joinSub(LecturaCore::horarios(), 'horario', 'grupo.id_grupo', '=', 'horario.id_grupo')
            ->where('inscripcion.id_alumno', $alumno->id_alumno)
            ->where('inscripcion.estatus', true)
            ->select(
                'curso.nombre as curso',
                'horario.dia_semana',
                'horario.hora_inicio',
                'horario.hora_fin',
                'horario.ubicacion'
            )
            ->orderBy('horario.dia_semana')
            ->get()
            ->map(function ($h) {
                $dias = [
                    1 => 'Lunes',
                    2 => 'Martes',
                    3 => 'Miércoles',
                    4 => 'Jueves',
                    5 => 'Viernes',
                    6 => 'Sábado',
                    7 => 'Domingo'
                ];
                $h->dia = $dias[$h->dia_semana] ?? 'Desconocido';
                $h->hora_inicio = substr($h->hora_inicio, 0, 5);
                $h->hora_fin = substr($h->hora_fin, 0, 5);
                unset($h->dia_semana);
                return $h;
            });

        $grupos = DB::query()->fromSub(LecturaCore::inscripciones(), 'inscripcion')
            ->joinSub(LecturaCore::grupos(), 'grupo', 'inscripcion.id_grupo', '=', 'grupo.id_grupo')
            ->joinSub(LecturaCore::cursos(), 'curso', 'grupo.id_curso', '=', 'curso.id_curso')
            ->joinSub(LecturaCore::personas('profesor'), 'profesor', 'grupo.id_profesor', '=', 'profesor.id_profesor')
            ->where('inscripcion.id_alumno', $alumno->id_alumno)
            ->where('inscripcion.estatus', true)
            ->select(
                'curso.nombre as curso',
                'grupo.codigo_grupo',
                'grupo.periodo',
                'grupo.fecha_inicio',
                'grupo.fecha_fin',
                DB::raw("CONCAT(profesor.nombre, ' ', profesor.apellido_p) as profesor")
            )
            ->get();

        $actInscripcion = DB::query()->fromSub(LecturaCore::inscripciones(), 'inscripcion')
            ->joinSub(LecturaCore::grupos(), 'grupo', 'inscripcion.id_grupo', '=', 'grupo.id_grupo')
            ->joinSub(LecturaCore::cursos(), 'curso', 'grupo.id_curso', '=', 'curso.id_curso')
            ->where('inscripcion.id_alumno', $alumno->id_alumno)
            ->select(
                DB::raw("'inscripcion' as tipo"),
                DB::raw("CONCAT('Inscripción a ', curso.nombre) as descripcion"),
                'inscripcion.fecha_asignacion as fecha'
            )
            ->orderByDesc('inscripcion.fecha_asignacion')
            ->limit(3);

        $actExtraescolar = DB::query()->fromSub(LecturaCore::inscripcionesExtra(), 'inscripcionextraescolar')
            ->joinSub(LecturaCore::extraescolares(), 'extraescolar', 'inscripcionextraescolar.id_extraescolar', '=', 'extraescolar.id_extraescolar')
            ->where('inscripcionextraescolar.id_alumno', $alumno->id_alumno)
            ->select(
                DB::raw("'extraescolar' as tipo"),
                DB::raw("CONCAT('Inscripción a extraescolar: ', extraescolar.nombre) as descripcion"),
                'inscripcionextraescolar.fecha_asignacion as fecha'
            )
            ->orderByDesc('inscripcionextraescolar.fecha_asignacion')
            ->limit(3);

        $actFactura = DB::query()->fromSub(LecturaCore::facturas(), 'factura')
            ->where('id_alumno', $alumno->id_alumno)
            ->select(
                DB::raw("'factura' as tipo"),
                DB::raw("COALESCE(concepto, 'Pago registrado') as descripcion"),
                'fecha_emision as fecha'
            )
            ->orderByDesc('fecha_emision')
            ->limit(3);

        $actividad = $actInscripcion
            ->union($actExtraescolar)
            ->union($actFactura)
            ->orderByDesc('fecha')
            ->limit(5)
            ->get();

        return response()->json([
            'alumno' => [
                'nombre' => trim($alumno->nombre . ' ' . $alumno->apellido_p),
                'inicial' => strtoupper(mb_substr($alumno->nombre, 0, 1)),
                'email' => $alumno->email,
                'puntaje' => $puntaje,
            ],
            'nivel' => $nivel,
            'puntaje_siguiente_nivel' => $puntajeSiguiente,
            'horarios' => $horarios,
            'grupos' => $grupos,
            'actividad_reciente' => $actividad,
        ]);
    }

    public function profesores(Request $request)
    {
        $alumno = $this->alumnoActual($request);

        if (!$alumno) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $profesores = DB::query()->fromSub(LecturaCore::personas('profesor'), 'profesor')
            ->where('profesor.id_sede', $alumno->id_sede)
            ->where('profesor.estatus', true)
            ->select(
                'profesor.id_profesor',
                'profesor.nombre',
                'profesor.apellido_p',
                'profesor.apellido_m',
                'profesor.email',
                'profesor.telefono',
                'profesor.puntaje'
            )
            ->get()
            ->map(function ($p) {
                $p->nombre_completo = trim($p->nombre . ' ' . $p->apellido_p . ' ' . ($p->apellido_m ?? ''));
                $p->inicial = strtoupper(mb_substr($p->nombre, 0, 1));

                $especialidades = DB::query()->fromSub(LecturaCore::grupos(), 'grupo')
                    ->joinSub(LecturaCore::cursos(), 'curso', 'grupo.id_curso', '=', 'curso.id_curso')
                    ->where('grupo.id_profesor', $p->id_profesor)
                    ->where('grupo.estatus', true)
                    ->pluck('curso.nombre')
                    ->unique()
                    ->values();

                $p->especialidades = $especialidades;
                return $p;
            });

        return response()->json([
            'profesores' => $profesores,
        ]);
    }

    public function extraescolares(Request $request)
    {
        $alumno = $this->alumnoActual($request);

        if (!$alumno) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // Auto-expirar facturas vencidas
        EscrituraCore::vencerCargos((int) $alumno->id_alumno);

        $inscritos = DB::query()->fromSub(LecturaCore::inscripcionesExtra(), 'inscripcionextraescolar')
            ->where('id_alumno', $alumno->id_alumno)
            ->where('status', true)
            ->pluck('id_extraescolar')
            ->toArray();

        $misInscripciones = DB::query()->fromSub(LecturaCore::inscripcionesExtra(), 'inscripcionextraescolar')
            ->joinSub(LecturaCore::extraescolares(), 'extraescolar', 'inscripcionextraescolar.id_extraescolar', '=', 'extraescolar.id_extraescolar')
            ->leftJoinSub(LecturaCore::facturas(), 'factura', 'factura.id_inscripcionextra', '=', 'inscripcionextraescolar.id_inscripcionextra')
            ->where('inscripcionextraescolar.id_alumno', $alumno->id_alumno)
            ->where('inscripcionextraescolar.status', true)
            ->select(
                'extraescolar.id_extraescolar',
                'extraescolar.nombre',
                'extraescolar.ubicacion',
                'extraescolar.fecha_inicio',
                'extraescolar.fecha_fin',
                'factura.vigencia',
                'factura.total_pago'
            )
            ->get();

        $catalogo = DB::query()->fromSub(LecturaCore::extraescolares(), 'extraescolar')
            ->where('estatus', true)
            ->where(function ($q) use ($inscritos) {
                $q->whereRaw('cupo_actual < cupo_maximo')
                    ->orWhereIn('id_extraescolar', $inscritos);
            })
            ->select(
                'id_extraescolar',
                'nombre',
                'descripcion',
                'ubicacion',
                'fecha_inicio',
                'fecha_fin',
                'cupo_maximo',
                'cupo_actual'
            )
            ->orderBy('nombre')
            ->get()
            ->map(function ($e) use ($inscritos, $alumno) {
                $e->inscrito   = in_array($e->id_extraescolar, $inscritos);
                $e->cupo_lleno = $e->cupo_actual >= $e->cupo_maximo;
                $e->cupo_disponible = $e->cupo_maximo - $e->cupo_actual;

                $e->vigencia_factura = null;
                if ($e->inscrito) {
                    $f = DB::query()->fromSub(LecturaCore::facturas(), 'factura')
                        ->joinSub(LecturaCore::inscripcionesExtra(), 'inscripcionextraescolar', 'factura.id_inscripcionextra', '=', 'inscripcionextraescolar.id_inscripcionextra')
                        ->where('inscripcionextraescolar.id_alumno', $alumno->id_alumno)
                        ->where('inscripcionextraescolar.id_extraescolar', $e->id_extraescolar)
                        ->where('inscripcionextraescolar.status', true)
                        ->select('factura.vigencia')
                        ->first();
                    $e->vigencia_factura = $f?->vigencia;
                }

                return $e;
            });

        return response()->json([
            'mis_inscripciones' => $misInscripciones,
            'catalogo' => $catalogo,
        ]);
    }

    public function inscribirExtraescolar(Request $request, $id)
    {
        $alumno = $this->alumnoActual($request);

        if (!$alumno) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $extra = DB::query()->fromSub(LecturaCore::extraescolares(), 'extraescolar')
            ->where('id_extraescolar', (int) $id)
            ->where('estatus', true)
            ->first();

        if (!$extra) {
            return response()->json(['message' => 'Actividad no encontrada'], 404);
        }

        if ($extra->cupo_actual >= $extra->cupo_maximo) {
            return response()->json(['message' => 'cupo_lleno'], 409);
        }

        $yaInscrito = DB::query()->fromSub(LecturaCore::inscripcionesExtra(), 'inscripcionextraescolar')
            ->where('id_alumno', $alumno->id_alumno)
            ->where('id_extraescolar', (int) $id)
            ->where('status', true)
            ->exists();

        if ($yaInscrito) {
            return response()->json(['message' => 'Ya estás inscrito en esta actividad'], 409);
        }

        try {
            DB::transaction(function () use ($alumno, $extra) {
                $idInscripcion = EscrituraCore::inscribir((int) $alumno->id_alumno, (int) $extra->id_grupo);
                EscrituraCore::cargo(
                    (int) $alumno->id_alumno, $idInscripcion, 'extraescolar',
                    'Inscripción a actividad extraescolar: ' . $extra->nombre, null,
                    (float) $extra->costo_base, (string) $extra->fecha_inicio
                );
            });
        } catch (\App\Services\ErrorDeDatos $e) {
            return response()->json(['message' => 'cupo_lleno'], 409);
        }

        return response()->json(['message' => 'Inscripción exitosa']);
    }

    public function cancelarExtraescolar(Request $request, $id)
    {
        $alumno = $this->alumnoActual($request);

        if (!$alumno) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $inscripcion = DB::query()->fromSub(LecturaCore::inscripcionesExtra(), 'inscripcionextraescolar')
            ->where('id_alumno', $alumno->id_alumno)
            ->where('id_extraescolar', (int) $id)
            ->where('status', true)
            ->first();

        if (!$inscripcion) {
            return response()->json(['message' => 'No estás inscrito en esta actividad'], 404);
        }

        $factura = DB::query()->fromSub(LecturaCore::facturas(), 'factura')
            ->where('id_inscripcionextra', $inscripcion->id_inscripcionextra)
            ->where('id_alumno', $alumno->id_alumno)
            ->first();

        if ($factura && $factura->vigencia === 'pagado') {
            return response()->json(['message' => 'Tu inscripción ya fue pagada. Para cancelarla comunícate con la sede.'], 409);
        }

        DB::transaction(function () use ($factura, $inscripcion) {
            if ($factura) {
                EscrituraCore::cancelarCargo((int) $factura->id_factura, 'Inscripción cancelada por el alumno');
            }
            EscrituraCore::darDeBaja((int) $inscripcion->id_inscripcionextra, 'Cancelada por el alumno');
        });

        return response()->json(['message' => 'Inscripción cancelada']);
    }
}