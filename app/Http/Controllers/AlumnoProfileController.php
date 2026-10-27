<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\Alumno;
use App\Services\SincronizadorCore;

use App\Models\Tutor;

class AlumnoProfileController extends Controller
{
    private function getAlumnoActual(): ?Alumno
    {
        $token = session('token');
        if (!$token) return null;

        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken || !($accessToken->tokenable instanceof Alumno)) return null;

        return $accessToken->tokenable;
    }

    private function calcularNivel(int $puntaje): array
    {
        return match (true) {
            $puntaje >= 3000 => ['numero' => 6, 'nombre' => 'Rey',     'emoji' => '♚'],
            $puntaje >= 1500 => ['numero' => 5, 'nombre' => 'Reina',   'emoji' => '♛'],
            $puntaje >= 800  => ['numero' => 4, 'nombre' => 'Torre',   'emoji' => '♜'],
            $puntaje >= 400  => ['numero' => 3, 'nombre' => 'Alfil',   'emoji' => '♝'],
            $puntaje >= 150  => ['numero' => 2, 'nombre' => 'Caballo', 'emoji' => '♞'],
            default          => ['numero' => 1, 'nombre' => 'Peón',    'emoji' => '♟'],
        };
    }



public function show()
{
    $alumno = $this->getAlumnoActual();
    if (!$alumno) return response()->json(['error' => 'No autenticado'], 401);

    $sede = DB::table('sede')->where('id_sede', $alumno->id_sede)->first();
    $tutor = Tutor::find($alumno->id_tutor);
    $nivel = $this->calcularNivel($alumno->puntaje ?? 0);
    $misPuntos = $alumno->puntaje ?? 0;

    // --- CÁLCULO DE POSICIONES PERSONALES ---
    // Posición Global: Contar cuántos puntajes únicos son mayores al mío y sumar 1
    $posicionGlobal = Alumno::where('puntaje', '>', $misPuntos)
        ->distinct('puntaje')
        ->count('puntaje') + 1;

    // Posición en Sede: Lo mismo pero filtrando por su sede
    $posicionSede = Alumno::where('id_sede', $alumno->id_sede)
        ->where('puntaje', '>', $misPuntos)
        ->distinct('puntaje')
        ->count('puntaje') + 1;

    // --- RANKING GLOBAL (Top 10 puntajes únicos) ---
    $topGlobal = Alumno::select('puntaje')->distinct()->whereNotNull('puntaje')
        ->orderBy('puntaje', 'desc')->limit(10)->pluck('puntaje');
    $minGlobal = $topGlobal->min() ?? 0;
    
    $rankingGlobal = Alumno::where('puntaje', '>=', $minGlobal)
        ->orderBy('puntaje', 'desc')->get()->map(function($a) {
            $n = $this->calcularNivel($a->puntaje ?? 0);
            return [
                'id_alumno' => $a->id_alumno,
                'nombre' => $a->nombre . ' ' . $a->apellido_p,
                'puntaje' => $a->puntaje,
                'nivel' => $n['numero']
            ];
        });

    // --- RANKING POR SEDE (Top 10 puntajes únicos) ---
    $topSede = Alumno::where('id_sede', $alumno->id_sede)->select('puntaje')
        ->distinct()->whereNotNull('puntaje')->orderBy('puntaje', 'desc')
        ->limit(10)->pluck('puntaje');
    $minSede = $topSede->min() ?? 0;

    $rankingSede = Alumno::where('id_sede', $alumno->id_sede)
        ->where('puntaje', '>=', $minSede)->orderBy('puntaje', 'desc')
        ->get()->map(function($a) {
            $n = $this->calcularNivel($a->puntaje ?? 0);
            return [
                'id_alumno' => $a->id_alumno,
                'nombre' => $a->nombre . ' ' . $a->apellido_p,
                'puntaje' => $a->puntaje,
                'nivel' => $n['numero']
            ];
        });

    return response()->json([
        'alumno' => $alumno,
        'posicion_global' => $posicionGlobal, // <--- Nueva
        'posicion_sede' => $posicionSede,     // <--- Nueva
        'sede' => $sede ? $sede->nombre : '—',
        'nivel' => $nivel,
        'ranking' => $rankingGlobal,
        'ranking_sede' => $rankingSede,
        'tutor' => $tutor
    ]);
}

    public function update(Request $request)
    {
        $alumno = $this->getAlumnoActual();
        if (!$alumno) return response()->json(['error' => 'No autenticado'], 401);

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:50',
            'apellido_p' => 'required|string|max:50',
            'apellido_m' => 'nullable|string|max:50',
            'email' => 'required|email|max:100|unique:alumno,email,' . $alumno->id_alumno . ',id_alumno',
            'telefono' => 'required|string|max:20',
            'calle' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:100',
            'estado_residencia' => 'nullable|string|max:100',
            'codigo_postal' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (SincronizadorCore::correoOcupado($request->email, 'alumno', $alumno->id_alumno)) {
            return response()->json(['errors' => ['email' => ['Ese correo ya tiene una cuenta en el sistema.']]], 422);
        }

        $alumno->nombre = $request->nombre;
        $alumno->apellido_p = $request->apellido_p;
        $alumno->apellido_m = $request->apellido_m;
        $alumno->email = $request->email;
        $alumno->telefono = $request->telefono;
        $alumno->calle = $request->calle;
        $alumno->ciudad = $request->ciudad;
        $alumno->estado_residencia = $request->estado_residencia;
        $alumno->codigo_postal = $request->codigo_postal;
        DB::transaction(function () use ($alumno) {
            $alumno->save();
            SincronizadorCore::persona('alumno', $alumno->id_alumno);
        });

        return response()->json(['message' => 'Perfil actualizado correctamente.']);
    }

    public function updatePassword(Request $request)
    {
        $alumno = $this->getAlumnoActual();
        if (!$alumno) return response()->json(['error' => 'No autenticado'], 401);

        $validator = Validator::make($request->all(), [
            'contrasena_antigua' => 'required',
            'contrasena_nueva' => 'required|min:8',
            'contrasena_confirmar' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Hash::check($request->contrasena_antigua, $alumno->contraseña)) {
            return response()->json(['error' => 'La contraseña antigua no es correcta.'], 422);
        }

        if ($request->contrasena_nueva !== $request->contrasena_confirmar) {
            return response()->json(['error' => 'La nueva contraseña y su confirmación no coinciden.'], 422);
        }

        $alumno->contraseña = Hash::make($request->contrasena_nueva);
        DB::transaction(function () use ($alumno) {
            $alumno->save();
            SincronizadorCore::persona('alumno', $alumno->id_alumno, true);
        });

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}
