<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\SincronizadorCore;

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
        $idPersonal     = session('id_personal');
        $idSedeSesion   = session('id_sede');
        $tipo       = session('tipo', 'personal');
        $idProfesor = session('id_profesor');

        $idSede  = $request->query('id_sede',  '');
        $idGrupo = $request->query('id_grupo', '');
        $nivel   = $request->query('nivel',    '');  // 1-6
        $q       = $request->query('q',        '');

        $query = DB::table('alumno')
            ->leftJoin('sede', 'alumno.id_sede', '=', 'sede.id_sede')
            ->leftJoin('inscripcion', function ($join) {
                $join->on('inscripcion.id_alumno', '=', 'alumno.id_alumno')
                     ->where('inscripcion.estatus', '=', true);
            })
            ->leftJoin('grupo', function ($join) {
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
    $gruposProfesor = DB::table('grupo')
        ->where('id_profesor', (int) $idProfesor)
        ->pluck('id_grupo');
    $query->whereIn('inscripcion.id_grupo', $gruposProfesor);
} elseif (!$administrativo) {
    $sedePersonal = DB::table('usuariosede')
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

        // ── Filtro búsqueda texto ───────────────────────────
        if ($q !== '') {
            $ql = '%' . mb_strtolower($q) . '%';
            $query->where(function ($sub) use ($ql) {
                $sub->whereRaw("LOWER(CONCAT(alumno.nombre, ' ', alumno.apellido_p)) LIKE ?", [$ql])
                    ->orWhereRaw("LOWER(alumno.nombre) LIKE ?", [$ql])
                    ->orWhereRaw("LOWER(alumno.apellido_p) LIKE ?", [$ql]);
            });
        }

        $alumnos = $query->get();

        // ── Calcular nivel en PHP (misma lógica que JS) ─────
        $resultado = $alumnos->map(function ($a) {
            $puntos = (int) ($a->puntaje ?? 0);
            $rango  = $this->calcularRango($puntos);

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

        // ── Filtro por nivel (post-map) ─────────────────────
        if ($nivel !== '' && is_numeric($nivel)) {
            $resultado = $resultado->filter(fn($a) => $a['nivel_num'] == (int) $nivel)->values();
        }

        // ── Estadísticas ────────────────────────────────────
        $total   = $resultado->count();
        $promPts = $total > 0 ? round($resultado->avg('puntaje'), 1) : 0;
        $promNiv = $total > 0 ? round($resultado->avg('nivel_num'), 1) : 0;

        return response()->json([
            'data'  => $resultado,
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
        $idPersonal     = session('id_personal');
        $idSedeSesion   = session('id_sede');
        $tipo       = session('tipo', 'personal');
        $idProfesor = session('id_profesor');

        $query = DB::table('grupo')
            ->leftJoin('curso', 'grupo.id_curso', '=', 'curso.id_curso')
            ->select('grupo.id_grupo', 'grupo.codigo_grupo', 'curso.nombre as nombre_curso')
            ->where('grupo.estatus', true)
            ->orderBy('grupo.codigo_grupo');

  if ($tipo === 'profesor' && $idProfesor) {
    $query->where('grupo.id_profesor', (int) $idProfesor);
} elseif (!$administrativo) {
    $sedePersonal = DB::table('usuariosede')
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

        $alumno = DB::table('alumno')->where('id_alumno', (int) $id)->first();

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

        DB::transaction(function () use ($id, $nuevoPuntaje) {
            DB::table('alumno')
                ->where('id_alumno', (int) $id)
                ->update(['puntaje' => $nuevoPuntaje]);
            SincronizadorCore::persona('alumno', (int) $id);   // rating en v2.2
        });

        $rango = $this->calcularRango($nuevoPuntaje);

        return response()->json([
            'message'      => 'Puntaje actualizado correctamente.',
            'puntaje_nuevo'=> $nuevoPuntaje,
            'nivel_num'    => $rango['numero'],
            'nivel_nombre' => $rango['nombre'],
            'nivel_emoji'  => $rango['emoji'],
        ]);
    }

    // -------------------------------------------------------
    // Misma lógica que calcularRango() en el JS del alumno
    // -------------------------------------------------------
    private function calcularRango(int $puntos): array
    {
        if ($puntos >= 3000) return ['numero' => 6, 'nombre' => 'Rey',     'emoji' => '♚'];
        if ($puntos >= 1500) return ['numero' => 5, 'nombre' => 'Reina',   'emoji' => '♛'];
        if ($puntos >= 800)  return ['numero' => 4, 'nombre' => 'Torre',   'emoji' => '♜'];
        if ($puntos >= 400)  return ['numero' => 3, 'nombre' => 'Alfil',   'emoji' => '♝'];
        if ($puntos >= 150)  return ['numero' => 2, 'nombre' => 'Caballo', 'emoji' => '♞'];
        return                      ['numero' => 1, 'nombre' => 'Peón',    'emoji' => '♟'];
    }
}
