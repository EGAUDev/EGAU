<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\LecturaCore;
use App\Services\SincronizadorCore;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\Alumno;
use App\Models\Factura;

class AlumnoPagosController extends Controller
{
    private function getAlumnoActual(): ?Alumno
    {
        $token = session('token');
        if (!$token) return null;

        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken || !($accessToken->tokenable instanceof Alumno))
            return null;

        return $accessToken->tokenable;
    }

    public function index()
    {
        $alumno = $this->getAlumnoActual();
        if (!$alumno) return response()->json(['error' => 'No autenticado'], 401);

        // Update expired invoices
        $vencidas = Factura::where('id_alumno', $alumno->id_alumno)
            ->where('vigencia', 'enproceso')
            ->where('fecha_limite', '<', now())
            ->pluck('id_factura');
        if ($vencidas->isNotEmpty()) {
            DB::transaction(function () use ($vencidas) {
                Factura::whereIn('id_factura', $vencidas)->update(['vigencia' => 'expirado']);
                SincronizadorCore::cargos($vencidas);
            });
        }

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
