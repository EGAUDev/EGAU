<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\SincronizadorCore;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\Alumno;

class AlumnoGruposController extends Controller
{
    private function getAlumnoActual(): ?Alumno
    {
        $token = session('token');
        if (!$token)
            return null;

        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken || !($accessToken->tokenable instanceof Alumno))
            return null;

        return $accessToken->tokenable;
    }

    /**
     * Convierte el umbral de puntaje almacenado en cursos.nivel
     * al nombre de la pieza de ajedrez correspondiente.
     */
    private static function nivelNombre(int $umbral): string
    {
        return match (true) {
            $umbral >= 3000 => '♚ Rey',
            $umbral >= 1500 => '♛ Reina',
            $umbral >= 800 => '♜ Torre',
            $umbral >= 400 => '♝ Alfil',
            $umbral >= 150 => '♞ Caballo',
            default => '♟ Peón',
        };
    }

    public function index()
    {
        $alumno = $this->getAlumnoActual();
        if (!$alumno)
            return response()->json(['error' => 'No autenticado'], 401);

        // Fetch user's enrolled groups
        $misInscripciones = DB::table('inscripcion')
            ->where('id_alumno', $alumno->id_alumno)
            ->where('estatus', true)
            ->pluck('id_grupo')
            ->toArray();

        $misGrupos = \App\Models\Academico\Grupo::with(['curso', 'profesor', 'horarios'])
            ->whereIn('id_grupo', $misInscripciones)
            ->whereHas('curso', function ($q) use ($alumno) {
                $q->where('id_sede', $alumno->id_sede);
            })
            ->get()
            ->map(function ($g) {
                $nivelDB = $g->curso ? (int) $g->curso->nivel : 0;
                return [
                    'id_grupo' => $g->id_grupo,
                    'curso_nombre' => $g->curso ? $g->curso->nombre : 'Sin curso',
                    'nivel' => self::nivelNombre($nivelDB),
                    'nivel_puntaje' => $nivelDB,
                    'profesor_nombre' => $g->profesor ? $g->profesor->nombre : 'Sin',
                    'profesor_apellido' => $g->profesor ? $g->profesor->apellido_p : 'asignar',
                    'horarios' => $g->horarios,
                    'inscrito' => true,
                    'cupo_actual' => $g->cupo_actual,
                    'cupo_maximo' => $g->cupo_maximo,
                ];
            });

        // El puntaje del alumno determina qué cursos puede ver:
        // Solo los grupos cuyo curso.nivel (umbral mínimo) sea <= puntaje del alumno
        $puntaje = $alumno->puntaje ?? 0;

        $gruposDisponibles = \App\Models\Academico\Grupo::with(['curso', 'profesor', 'horarios'])
            ->whereNotIn('id_grupo', $misInscripciones)
            ->where('estatus', true)
            ->whereHas('curso', function ($q) use ($puntaje, $alumno) {
                $q->where('nivel', '<=', $puntaje)
                  ->where('id_sede', $alumno->id_sede);
            })
            ->get()
            ->map(function ($g) {
                $nivelDB = $g->curso ? (int) $g->curso->nivel : 0;
                return [
                    'id_grupo' => $g->id_grupo,
                    'curso_nombre' => $g->curso ? $g->curso->nombre : 'Sin curso',
                    'nivel' => self::nivelNombre($nivelDB),
                    'nivel_puntaje' => $nivelDB,
                    'profesor_nombre' => $g->profesor ? $g->profesor->nombre : 'Sin',
                    'profesor_apellido' => $g->profesor ? $g->profesor->apellido_p : 'asignar',
                    'horarios' => $g->horarios,
                    'cupo_actual' => $g->cupo_actual,
                    'cupo_maximo' => $g->cupo_maximo,
                    'inscrito' => false,
                    'cupo_lleno' => $g->cupo_actual >= $g->cupo_maximo,
                ];
            });

        return response()->json([
            'mis_grupos' => $misGrupos,
            'grupos_disponibles' => $gruposDisponibles,
            'nivel_alumno' => $puntaje,
            'nivel_nombre' => self::nivelNombre($puntaje),
        ]);
    }

    public function accion(Request $request, $id, $accion)
    {
        $alumno = $this->getAlumnoActual();
        if (!$alumno)
            return response()->json(['message' => 'No autenticado'], 401);

        $grupo = DB::table('grupo')->where('id_grupo', $id)->first();
        if (!$grupo)
            return response()->json(['message' => 'Grupo no encontrado'], 404);

        if ($accion === 'inscribir') {
            $curso = DB::table('curso')->where('id_curso', $grupo->id_curso)->first();
            if (!$curso) {
                return response()->json(['message' => 'Curso no válido.'], 400);
            }

            // Validar Sede
            if ($curso->id_sede != $alumno->id_sede) {
                return response()->json(['message' => 'Este grupo pertenece a una sede distinta a la tuya.'], 403);
            }

            // Validar Nivel
            $puntaje = $alumno->puntaje ?? 0;
            if ($curso->nivel > $puntaje) {
                return response()->json(['message' => 'No tienes el nivel suficiente para inscribirte a este grupo.'], 403);
            }

            if ($grupo->cupo_actual >= $grupo->cupo_maximo) {
                return response()->json(['message' => 'El grupo está lleno.'], 400);
            }

            $yaInscrito = DB::table('inscripcion')
                ->where('id_alumno', $alumno->id_alumno)
                ->where('id_grupo', $id)
                ->where('estatus', true)
                ->exists();

            if ($yaInscrito)
                return response()->json(['message' => 'Ya estás inscrito en este grupo.'], 400);

            DB::transaction(function () use ($alumno, $id, $grupo, $curso) {
                $id_inscripcion = DB::table('inscripcion')->insertGetId([
                    'id_alumno' => $alumno->id_alumno,
                    'id_grupo' => $id,
                    'fecha_asignacion' => now(),
                    'estatus' => true
                ], 'id_inscripcion');

                DB::table('grupo')->where('id_grupo', $id)->increment('cupo_actual');

                $id_factura = DB::table('factura')->insertGetId([
                    'id_alumno' => $alumno->id_alumno,
                    'id_inscripcion' => $id_inscripcion,
                    'fecha_emision' => now(),
                    'fecha_limite' => now()->addMonth(),
                    'total_pago' => $curso->costo_base ?? 0,
                    'vigencia' => 'enproceso',
                    'descripcion' => $curso->descripcion ?? 'Inscripción a curso',
                    'concepto' => 'Pago de inscripcion a grupo ' . $grupo->codigo_grupo
                ], 'id_factura');
                SincronizadorCore::cargo((int) $id_factura);
            });

            return response()->json(['message' => '¡Te has inscrito al grupo exitosamente!']);

        } elseif ($accion === 'cancelar') {
            $inscripcion = DB::table('inscripcion')
                ->where('id_alumno', $alumno->id_alumno)
                ->where('id_grupo', $id)
                ->where('estatus', true)
                ->first();

            if (!$inscripcion) {
                return response()->json(['message' => 'No estás inscrito en este grupo.'], 404);
            }

            $factura = DB::table('factura')
                ->where('id_inscripcion', $inscripcion->id_inscripcion)
                ->where('id_alumno', $alumno->id_alumno)
                ->first();

            $forzar = $request->input('forzar', false);

            if ($factura && $factura->vigencia === 'pagado' && !$forzar) {
                return response()->json([
                    'requires_confirmation' => true,
                    'message' => 'Tu inscripción ya fue pagada. ¿Estás en serio de que deseas darte de baja del grupo? En caso afirmativo, la factura permanecerá como pagada.'
                ], 400);
            }

            DB::transaction(function () use ($inscripcion, $id, $factura) {
                DB::table('inscripcion')
                    ->where('id_inscripcion', $inscripcion->id_inscripcion)
                    ->update(['estatus' => false]);

                DB::table('grupo')->where('id_grupo', $id)->decrement('cupo_actual');

                if ($factura && $factura->vigencia !== 'pagado') {
                    DB::table('factura')
                        ->where('id_factura', $factura->id_factura)
                        ->update(['vigencia' => 'cancelado']);
                    SincronizadorCore::cargo((int) $factura->id_factura);
                }
            });

            return response()->json(['message' => 'Inscripción cancelada exitosamente.']);
        }

        return response()->json(['message' => 'Acción inválida'], 400);
    }
}
