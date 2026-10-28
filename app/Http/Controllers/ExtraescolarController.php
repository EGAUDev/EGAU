<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\LecturaCore;
use App\Services\EscrituraCore;

class ExtraescolarController extends Controller
{
    // -------------------------------------------------------
    // GET /admin/extraescolares?q=
    // -------------------------------------------------------
    public function listar(Request $request)
    {
        $q = $request->query('q', '');

        $query = DB::query()->fromSub(LecturaCore::extraescolares(), 'extraescolar')
            ->select(
                'extraescolar.*',
                'extraescolar.cupo_actual AS inscritos_activos'
            )
            ->orderBy('extraescolar.id_extraescolar', 'desc');

        if ($q !== '') {
            $ql = '%' . strtolower($q) . '%';
            $query->where(function ($sub) use ($ql) {
                $sub->whereRaw('LOWER(extraescolar.nombre) LIKE ?', [$ql])
                    ->orWhereRaw('LOWER(extraescolar.ubicacion) LIKE ?', [$ql])
                    ->orWhereRaw('LOWER(extraescolar.descripcion) LIKE ?', [$ql]);
            });
        }

        $actividades = $query->get();

        return response()->json(['total' => $actividades->count(), 'data' => $actividades]);
    }

    // -------------------------------------------------------
    // GET /admin/extraescolares/{id}
    // Incluye lista de inscritos activos
    // -------------------------------------------------------
    public function obtener($id)
    {
        $actividad = DB::query()->fromSub(LecturaCore::extraescolares(), 'extraescolar')
            ->where('id_extraescolar', (int) $id)
            ->first();

        if (!$actividad) {
            return response()->json(['error' => 'Actividad no encontrada'], 404);
        }

        // Alumnos inscritos activos
        $inscritos = DB::query()->fromSub(LecturaCore::inscripcionesExtra(), 'inscripcionextraescolar')
            ->joinSub(LecturaCore::personas('alumno'), 'alumno', 'inscripcionextraescolar.id_alumno', '=', 'alumno.id_alumno')
            ->select(
                'inscripcionextraescolar.id_inscripcionextra',
                'alumno.id_alumno',
                'alumno.nombre',
                'alumno.apellido_p',
                'alumno.apellido_m',
                'alumno.email',
                'alumno.telefono',
                'inscripcionextraescolar.fecha_asignacion',
                'inscripcionextraescolar.status'
            )
            ->where('inscripcionextraescolar.id_extraescolar', (int) $id)
            ->where('inscripcionextraescolar.status', true)
            ->orderBy('alumno.apellido_p')
            ->get();

        return response()->json([
            'actividad' => $actividad,
            'inscritos' => $inscritos,
        ]);
    }

    // -------------------------------------------------------
    // POST /admin/extraescolares
    // -------------------------------------------------------
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre'          => 'required|string|max:20',
            'cupo_maximo'     => 'nullable|integer|min:1',
            'duracion_semanas' => 'required|integer|min:1',
            'costo_base'      => 'nullable|numeric|min:0',
            'fecha_inicio'    => 'required|date',
            'fecha_fin'       => 'required|date|after_or_equal:fecha_inicio',
            'estatus'         => 'required|boolean',
            'ubicacion'       => 'required|string|max:20',
            'descripcion'     => 'nullable|string',
            'requisitos'      => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $id = DB::transaction(fn () => EscrituraCore::extraescolar(null, $request->only([
            'nombre', 'cupo_maximo', 'duracion_semanas', 'costo_base', 'fecha_inicio', 'fecha_fin',
            'estatus', 'ubicacion', 'descripcion', 'requisitos',
        ])));

        return response()->json(['message' => 'Actividad creada correctamente.', 'id' => $id], 201);
    }

    // -------------------------------------------------------
    // PUT /admin/extraescolares/{id}
    // -------------------------------------------------------
    public function editar(Request $request, $id)
    {
        $actividad = DB::query()->fromSub(LecturaCore::extraescolares(), 'extraescolar')->where('id_extraescolar', (int) $id)->first();

        if (!$actividad) {
            return response()->json(['error' => 'Actividad no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre'          => 'required|string|max:20',
            'cupo_maximo'     => 'nullable|integer|min:1',
            'duracion_semanas' => 'required|integer|min:1',
            'costo_base'      => 'nullable|numeric|min:0',
            'fecha_inicio'    => 'required|date',
            'fecha_fin'       => 'required|date|after_or_equal:fecha_inicio',
            'estatus'         => 'required|boolean',
            'ubicacion'       => 'required|string|max:20',
            'descripcion'     => 'nullable|string',
            'requisitos'      => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::transaction(fn () => EscrituraCore::extraescolar((int) $id, $request->only([
            'nombre', 'cupo_maximo', 'duracion_semanas', 'costo_base', 'fecha_inicio', 'fecha_fin',
            'estatus', 'ubicacion', 'descripcion', 'requisitos',
        ])));

        return response()->json(['message' => 'Actividad actualizada correctamente.']);
    }

    // -------------------------------------------------------
    // DELETE /admin/extraescolares/{id}
    // Solo elimina si no hay inscripciones activas
    // -------------------------------------------------------
    public function eliminar($id)
    {
        $actividad = DB::query()->fromSub(LecturaCore::extraescolares(), 'extraescolar')->where('id_extraescolar', (int) $id)->first();

        if (!$actividad) {
            return response()->json(['error' => 'Actividad no encontrada'], 404);
        }

        $tieneInscritos = DB::query()->fromSub(LecturaCore::inscripcionesExtra(), 'inscripcionextraescolar')
            ->where('id_extraescolar', (int) $id)
            ->where('status', true)
            ->exists();

        if ($tieneInscritos) {
            return response()->json([
                'error' => 'No se puede eliminar la actividad porque tiene alumnos inscritos activos. Da de baja a los alumnos primero.'
            ], 409);
        }

        try {
            DB::transaction(fn () => EscrituraCore::eliminarExtraescolar((int) $id));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23503') {
                return response()->json([
                    'error' => 'No se puede eliminar porque la actividad tiene historial de inscripciones o pagos. Puedes desactivarla cambiando su estatus a inactivo.'
                ], 409);
            }
            throw $e;
        }

        return response()->json(['message' => 'Actividad eliminada correctamente.']);
    }

    // -------------------------------------------------------
    // PUT /admin/extraescolares/baja/{idInscripcion}
    // Da de baja un alumno de la actividad (status = false)
    // -------------------------------------------------------
    public function darDeBajaAlumno($idInscripcion)
    {
        $inscripcion = DB::query()->fromSub(LecturaCore::inscripcionesExtra(), 'inscripcionextraescolar')
            ->where('id_inscripcionextra', (int) $idInscripcion)->first();

        if (!$inscripcion) {
            return response()->json(['error' => 'Inscripción no encontrada'], 404);
        }

        EscrituraCore::darDeBaja((int) $idInscripcion, 'Baja desde administración');

        return response()->json(['message' => 'Alumno dado de baja correctamente.']);
    }
}
