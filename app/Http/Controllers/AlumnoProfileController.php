<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\LecturaCore;
use App\Services\AccesoCore;
use App\Services\EscrituraCore;

class AlumnoProfileController extends Controller
{
    /** La persona con sesión abierta, con las columnas de LecturaCore::personas('alumno'). */
    private function getAlumnoActual(): ?object
    {
        $id = AccesoCore::personaActual('alumno');
        return $id ? DB::query()->fromSub(LecturaCore::personas('alumno'), 'alumno')->where('id_alumno', $id)->first() : null;
    }

    private static function passwordCorrecta(int $idPersona, string $password): bool
    {
        $hash = DB::table('core.usuario')->where('id_persona', $idPersona)->value('password_hash');
        return $hash && Hash::check($password, $hash);
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



/** Alumnos leídos de v2.2 con las columnas de public.alumno. */
private static function alumnos()
{
    return DB::query()->fromSub(LecturaCore::personas('alumno'), 'alumno');
}

public function show()
{
    $alumno = $this->getAlumnoActual();
    if (!$alumno) return response()->json(['error' => 'No autenticado'], 401);

    $sede = DB::query()->fromSub(LecturaCore::sedes(), 'sede')->where('id_sede', $alumno->id_sede)->first();
    $tutor = $alumno->id_tutor
        ? DB::query()->fromSub(LecturaCore::tutores(), 'tutor')->where('id_tutor', $alumno->id_tutor)->first()
        : null;
    $nivel = $this->calcularNivel($alumno->puntaje ?? 0);
    $misPuntos = $alumno->puntaje ?? 0;

    // --- CÁLCULO DE POSICIONES PERSONALES ---
    // Posición Global: Contar cuántos puntajes únicos son mayores al mío y sumar 1
    $posicionGlobal = self::alumnos()->where('puntaje', '>', $misPuntos)
        ->distinct('puntaje')
        ->count('puntaje') + 1;

    // Posición en Sede: Lo mismo pero filtrando por su sede
    $posicionSede = self::alumnos()->where('id_sede', $alumno->id_sede)
        ->where('puntaje', '>', $misPuntos)
        ->distinct('puntaje')
        ->count('puntaje') + 1;

    // --- RANKING GLOBAL (Top 10 puntajes únicos) ---
    $topGlobal = self::alumnos()->select('puntaje')->distinct()->whereNotNull('puntaje')
        ->orderBy('puntaje', 'desc')->limit(10)->pluck('puntaje');
    $minGlobal = $topGlobal->min() ?? 0;
    
    $rankingGlobal = self::alumnos()->where('puntaje', '>=', $minGlobal)
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
    $topSede = self::alumnos()->where('id_sede', $alumno->id_sede)->select('puntaje')
        ->distinct()->whereNotNull('puntaje')->orderBy('puntaje', 'desc')
        ->limit(10)->pluck('puntaje');
    $minSede = $topSede->min() ?? 0;

    $rankingSede = self::alumnos()->where('id_sede', $alumno->id_sede)
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
            'email' => 'required|email|max:100',
            'telefono' => 'required|string|max:20',
            'calle' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:100',
            'estado_residencia' => 'nullable|string|max:100',
            'codigo_postal' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (EscrituraCore::correoOcupado($request->email, (int) $alumno->id_alumno)) {
            return response()->json(['errors' => ['email' => ['Ese correo ya tiene una cuenta en el sistema.']]], 422);
        }

        DB::transaction(fn () => EscrituraCore::persona('alumno', (int) $alumno->id_alumno, $request->only([
            'nombre', 'apellido_p', 'apellido_m', 'email', 'telefono', 'calle', 'ciudad', 'estado_residencia', 'codigo_postal',
        ])));

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

        if (!self::passwordCorrecta((int) $alumno->id_alumno, $request->contrasena_antigua)) {
            return response()->json(['error' => 'La contraseña antigua no es correcta.'], 422);
        }

        if ($request->contrasena_nueva !== $request->contrasena_confirmar) {
            return response()->json(['error' => 'La nueva contraseña y su confirmación no coinciden.'], 422);
        }

        EscrituraCore::password((int) $alumno->id_alumno, $request->contrasena_nueva);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}
