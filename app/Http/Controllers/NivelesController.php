<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\Buscador;
use App\Services\LecturaCore;
use App\Services\Niveles;
use App\Services\EscrituraCore;

class NivelesController extends Controller
{
    // -------------------------------------------------------
    // GET /admin/niveles/alumnos
    // Params opcionales: id_sede, id_grupo, nivel (1-6), q
    // Respeta si administrativo=false → solo su sede (usuariosede)
    // -------------------------------------------------------
    public function listar(Request $request)
    {
        $administrativo = filter_var(session('administrativo', false), FILTER_VALIDATE_BOOLEAN);
        $idPersonal     = session('id_persona');
        $idSedeSesion   = session('id_sede');
        $tipo       = session('tipo', 'personal');
        $idProfesor = session('id_profesor');

        $idSede  = $request->query('id_sede',  '');
        $idGrupo = $request->query('id_grupo', '');
        $nivel   = $request->query('nivel',    '');  // 1-6
        $q       = $request->query('q',        '');

        $query = DB::query()->fromSub(LecturaCore::personas('alumno'), 'alumno')
            ->leftJoinSub(LecturaCore::sedes(), 'sede', 'alumno.id_sede', '=', 'sede.id_sede')
            ->leftJoinSub(LecturaCore::inscripciones(), 'inscripcion', function ($join) {
                $join->on('inscripcion.id_alumno', '=', 'alumno.id_alumno')
                     ->where('inscripcion.estatus', '=', true);
            })
            ->leftJoinSub(LecturaCore::grupos(), 'grupo', function ($join) {
                $join->on('grupo.id_grupo', '=', 'inscripcion.id_grupo')
                     ->where('grupo.estatus', '=', true);
            })
            ->select(
                'alumno.id_alumno',
                'alumno.nombre',
                'alumno.apellido_p',
                'alumno.apellido_m',
                'alumno.puntaje',
                'alumno.estatus',
                'alumno.id_sede',
                'sede.nombre AS nombre_sede',
                'grupo.id_grupo',
                'grupo.codigo_grupo'
            )
            ->where('alumno.estatus', true)
            ->orderBy('alumno.apellido_p')
            ->orderBy('alumno.nombre');

        // ── Restricción por sede ────────────────────────────
if ($tipo === 'profesor' && $idProfesor) {
    $gruposProfesor = DB::query()->fromSub(LecturaCore::grupos(), 'grupo')
        ->where('id_profesor', (int) $idProfesor)
        ->pluck('id_grupo');
    $query->whereIn('inscripcion.id_grupo', $gruposProfesor);
} elseif (!$administrativo) {
    $sedePersonal = DB::query()->fromSub(LecturaCore::sedesPersonal(), 'usuariosede')
        ->where('id_personal', $idPersonal)
        ->value('id_sede');
    $sedeEfectiva = $sedePersonal ?? $idSedeSesion;
    if ($sedeEfectiva) {
        $query->where('alumno.id_sede', $sedeEfectiva);
    }
} elseif ($idSede !== '') {
    $query->where('alumno.id_sede', (int) $idSede);
}


        // ── Filtro grupo ────────────────────────────────────
        if ($idGrupo !== '') {
            $query->where('grupo.id_grupo', (int) $idGrupo);
        }

        // ── Filtro búsqueda texto (cada palabra en nombre o apellidos, sin acentos) ──
        Buscador::filtrar($query, $q, ['alumno.nombre', 'alumno.apellido_p', 'alumno.apellido_m']);

        // ── Filtro por nivel (1-6), en la consulta con los rangos de academico.nivel ──
        if ($nivel !== '' && ($n = Niveles::buscar($nivel))) {
            Niveles::dentro($query, 'alumno.puntaje', $n);
        }

        // ── Estadísticas sobre todo el resultado (no solo la página) ──
        $puntajes = (clone $query)->pluck('alumno.puntaje')->map(fn($p) => (int) ($p ?? 0));
        $total    = $puntajes->count();
        $promPts  = $total > 0 ? round($puntajes->avg(), 1) : 0;
        $promNiv  = $total > 0 ? round($puntajes->avg(fn($p) => Niveles::dePuntaje($p)['numero']), 1) : 0;

        [$alumnos, $meta] = Buscador::obtener($query, $request);

        $resultado = $alumnos->map(function ($a) {
            $puntos = (int) ($a->puntaje ?? 0);
            $rango  = Niveles::dePuntaje($puntos);

            return [
                'id_alumno'    => $a->id_alumno,
                'nombre'       => trim("{$a->nombre} {$a->apellido_p} " . ($a->apellido_m ?? '')),
                'nombre_sede'  => $a->nombre_sede ?? '—',
                'id_sede'      => $a->id_sede,
                'puntaje'      => $puntos,
                'id_grupo'     => $a->id_grupo,
                'codigo_grupo' => $a->codigo_grupo ?? '—',
                'nivel_num'    => $rango['numero'],
                'nivel_nombre' => $rango['nombre'],
                'nivel_emoji'  => $rango['emoji'],
            ];
        });

        return response()->json([
            'data'  => $resultado,
            'meta'  => $meta,
            'stats' => [
                'total'       => $total,
                'prom_puntos' => $promPts,
                'prom_nivel'  => $promNiv,
            ],
        ]);
    }

    // -------------------------------------------------------
    // GET /admin/niveles/grupos
    // Lista grupos activos para el dropdown de filtro
    // -------------------------------------------------------
    public function grupos(Request $request)
    {
        $administrativo = filter_var(session('administrativo', false), FILTER_VALIDATE_BOOLEAN);
        $idPersonal     = session('id_persona');
        $idSedeSesion   = session('id_sede');
        $tipo       = session('tipo', 'personal');
        $idProfesor = session('id_profesor');

        $query = DB::query()->fromSub(LecturaCore::grupos(), 'grupo')
            ->leftJoinSub(LecturaCore::cursos(), 'curso', 'grupo.id_curso', '=', 'curso.id_curso')
            ->select('grupo.id_grupo', 'grupo.codigo_grupo', 'curso.nombre as nombre_curso')
            ->where('grupo.estatus', true)
            ->orderBy('grupo.codigo_grupo');

  if ($tipo === 'profesor' && $idProfesor) {
    $query->where('grupo.id_profesor', (int) $idProfesor);
} elseif (!$administrativo) {
    $sedePersonal = DB::query()->fromSub(LecturaCore::sedesPersonal(), 'usuariosede')
        ->where('id_personal', $idPersonal)
        ->value('id_sede');
    $sedeEfectiva = $sedePersonal ?? $idSedeSesion;
    if ($sedeEfectiva) {
        $query->where('curso.id_sede', $sedeEfectiva);
    }
}

$grupos = $query->get();

return response()->json(['data' => $grupos]);
}


    // -------------------------------------------------------
    // PUT /admin/niveles/alumnos/{id}/puntaje
    // Modifica el puntaje de un alumno (sumar o restar)
    // -------------------------------------------------------
    public function editarPuntaje(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'operacion' => 'required|in:sumar,restar',
            'cantidad'  => 'required|integer|min:1|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $alumno = DB::query()->fromSub(LecturaCore::personas('alumno'), 'alumno')->where('id_alumno', (int) $id)->first();

        if (!$alumno) {
            return response()->json(['error' => 'Alumno no encontrado.'], 404);
        }

        $puntajeActual = (int) ($alumno->puntaje ?? 0);
        $cantidad      = (int) $request->cantidad;

        if ($request->operacion === 'sumar') {
            $nuevoPuntaje = $puntajeActual + $cantidad;
        } else {
            $nuevoPuntaje = max(0, $puntajeActual - $cantidad);
        }

        DB::transaction(fn () => EscrituraCore::puntaje((int) $id, $nuevoPuntaje, 'Ajuste de puntaje (' . $request->operacion . ' ' . $cantidad . ')'));
        $nuevoPuntaje = (int) DB::table('core.persona')->where('id', (int) $id)->value('rating');   // v2.2 topa el rating

        $rango = Niveles::dePuntaje($nuevoPuntaje);

        return response()->json([
            'message'      => 'Puntaje actualizado correctamente.',
            'puntaje_nuevo'=> $nuevoPuntaje,
            'nivel_num'    => $rango['numero'],
            'nivel_nombre' => $rango['nombre'],
            'nivel_emoji'  => $rango['emoji'],
        ]);
    }
}
