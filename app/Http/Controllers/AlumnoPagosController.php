<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\LecturaCore;
use App\Services\AccesoCore;
use App\Services\EscrituraCore;

class AlumnoPagosController extends Controller
{
    /** El alumno con sesión abierta, con las columnas de LecturaCore::personas('alumno'). */
    private function getAlumnoActual(): ?object
    {
        $idAlumno = AccesoCore::personaActual('alumno');
        return $idAlumno ? DB::query()->fromSub(LecturaCore::personas('alumno'), 'alumno')->where('id_alumno', $idAlumno)->first() : null;
    }

    public function index()
    {
        $alumno = $this->getAlumnoActual();
        if (!$alumno) return response()->json(['error' => 'No autenticado'], 401);

        // Update expired invoices
        EscrituraCore::vencerCargos((int) $alumno->id_alumno);

        $facturas = DB::query()->fromSub(LecturaCore::facturas(), 'factura')
            ->where('id_alumno', $alumno->id_alumno)
            ->where('vigencia', '!=', 'cancelado')
            ->orderBy('fecha_emision', 'desc')
            ->get();

        $idSede = DB::query()->fromSub(LecturaCore::personas('alumno'), 'alumno')
            ->where('id_alumno', $alumno->id_alumno)->value('id_sede');
        $sede = DB::query()->fromSub(LecturaCore::sedes(), 'sede')->where('id_sede', $idSede)->first();

        return response()->json([
            'facturas' => $facturas,
            'sede' => $sede
        ]);
    }
}
