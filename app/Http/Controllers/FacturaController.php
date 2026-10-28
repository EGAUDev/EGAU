<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\LecturaCore;
use App\Services\EscrituraCore;

class FacturaController extends Controller
{
    // -------------------------------------------------------
    // GET /admin/facturas
    // Parámetros opcionales: id_sede, vigencia, tipo (grupo|extraescolar), q
    // -------------------------------------------------------
    public function listar(Request $request)
    {
        $idSede  = $request->query('id_sede', '');
        $vigencia = $request->query('vigencia', '');
        $tipo    = $request->query('tipo', '');  // 'grupo' | 'extraescolar' | ''
        $q       = $request->query('q', '');

        $query = DB::query()->fromSub(LecturaCore::facturas(), 'factura')
            ->leftJoinSub(LecturaCore::personas('alumno'), 'alumno', 'factura.id_alumno', '=', 'alumno.id_alumno')
            ->leftJoinSub(LecturaCore::sedes(), 'sede', 'alumno.id_sede', '=', 'sede.id_sede')
            ->leftJoinSub(LecturaCore::inscripciones(), 'inscripcion', 'factura.id_inscripcion', '=', 'inscripcion.id_inscripcion')
            ->leftJoinSub(LecturaCore::inscripcionesExtra(), 'inscripcionextraescolar', 'factura.id_inscripcionextra', '=', 'inscripcionextraescolar.id_inscripcionextra')
            ->leftJoinSub(LecturaCore::extraescolares(), 'extraescolar', 'inscripcionextraescolar.id_extraescolar', '=', 'extraescolar.id_extraescolar')
            ->select(
                'factura.id_factura',
                'factura.id_alumno',
                'factura.id_inscripcion',
                'factura.id_inscripcionextra',
                'factura.fecha_emision',
                'factura.fecha_limite',
                'factura.total_pago',
                'factura.vigencia',
                'factura.descripcion',
                'factura.concepto',
                DB::raw("CONCAT(alumno.nombre, ' ', alumno.apellido_p, ' ', COALESCE(alumno.apellido_m,'')) AS nombre_alumno"),
                'sede.id_sede',
                'sede.nombre AS nombre_sede',
                'extraescolar.nombre AS nombre_extraescolar'
            )
            ->orderBy('factura.id_factura', 'desc');

        // Filtro sede (solo si el admin tiene administrativo=true)
        if ($idSede !== '') {
            $query->where('alumno.id_sede', (int) $idSede);
        }

        // Filtro vigencia
        if ($vigencia !== '') {
            $query->where('factura.vigencia', $vigencia);
        }

        // Filtro tipo
        if ($tipo === 'grupo') {
            $query->whereNotNull('factura.id_inscripcion')->whereNull('factura.id_inscripcionextra');
        } elseif ($tipo === 'extraescolar') {
            $query->whereNull('factura.id_inscripcion')->whereNotNull('factura.id_inscripcionextra');
        }

        // Buscador texto
        if ($q !== '') {
            $ql = '%' . strtolower($q) . '%';
            $query->where(function ($sub) use ($ql) {
                $sub->whereRaw("LOWER(CONCAT(alumno.nombre, ' ', alumno.apellido_p)) LIKE ?", [$ql])
                    ->orWhereRaw('LOWER(factura.concepto) LIKE ?', [$ql])
                    ->orWhereRaw('LOWER(factura.descripcion) LIKE ?', [$ql]);
                if (is_numeric(trim($ql, '%'))) {
                    $sub->orWhere('factura.id_factura', (int) trim($ql, '%'));
                }
            });
        }

        $facturas = $query->get();

        $data = $facturas->map(function ($f) {
            $tipo = $f->id_inscripcion ? 'grupo' : ($f->id_inscripcionextra ? 'extraescolar' : 'otro');
            return [
                'id_factura'          => $f->id_factura,
                'nombre_alumno'       => trim($f->nombre_alumno),
                'nombre_sede'         => $f->nombre_sede ?? '—',
                'concepto'            => $f->concepto,
                'total_pago'          => $f->total_pago,
                'vigencia'            => $f->vigencia,
                'fecha_emision'       => $f->fecha_emision,
                'fecha_limite'        => $f->fecha_limite,
                'tipo'                => $tipo,
                'nombre_extraescolar' => $f->nombre_extraescolar,
            ];
        });

        return response()->json(['total' => $data->count(), 'data' => $data]);
    }

    // -------------------------------------------------------
    // GET /admin/facturas/{id}
    // Retorna todos los campos de la factura con datos relacionados
    // -------------------------------------------------------
    public function obtener($id)
    {
        $f = DB::query()->fromSub(LecturaCore::facturas(), 'factura')
            ->leftJoinSub(LecturaCore::personas('alumno'), 'alumno', 'factura.id_alumno', '=', 'alumno.id_alumno')
            ->leftJoinSub(LecturaCore::sedes(), 'sede', 'alumno.id_sede', '=', 'sede.id_sede')
            ->leftJoinSub(LecturaCore::inscripciones(), 'inscripcion', 'factura.id_inscripcion', '=', 'inscripcion.id_inscripcion')
            ->leftJoinSub(LecturaCore::inscripcionesExtra(), 'inscripcionextraescolar', 'factura.id_inscripcionextra', '=', 'inscripcionextraescolar.id_inscripcionextra')
            ->leftJoinSub(LecturaCore::extraescolares(), 'extraescolar', 'inscripcionextraescolar.id_extraescolar', '=', 'extraescolar.id_extraescolar')
            ->select(
                'factura.*',
                DB::raw("CONCAT(alumno.nombre, ' ', alumno.apellido_p, ' ', COALESCE(alumno.apellido_m,'')) AS nombre_alumno"),
                'alumno.email AS email_alumno',
                'sede.nombre AS nombre_sede',
                'extraescolar.nombre AS nombre_extraescolar',
                'extraescolar.id_extraescolar'
            )
            ->where('factura.id_factura', (int) $id)
            ->first();

        if (!$f) {
            return response()->json(['error' => 'Factura no encontrada'], 404);
        }

        return response()->json($f);
    }

    // -------------------------------------------------------
    // PUT /admin/facturas/{id}/vigencia
    // Solo permite cambiar vigencia a 'pagado' o 'cancelado'
    // -------------------------------------------------------
    public function actualizarVigencia(Request $request, $id)
    {
        $vigencia = $request->input('vigencia');

        if (!in_array($vigencia, ['pagado', 'cancelado'], true)) {
            return response()->json(['error' => 'Vigencia inválida. Solo se permite "pagado" o "cancelado".'], 422);
        }

        if (!DB::table('finanzas.cargo')->where('id', (int) $id)->exists()) {
            return response()->json(['error' => 'Factura no encontrada'], 404);
        }

        DB::transaction(fn () => $vigencia === 'pagado'
            ? EscrituraCore::pagarCargo((int) $id)
            : EscrituraCore::cancelarCargo((int) $id));

        return response()->json(['message' => 'Vigencia actualizada correctamente.', 'vigencia' => $vigencia]);
    }
}
