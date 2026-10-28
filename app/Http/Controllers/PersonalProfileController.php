<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\LecturaCore;
use App\Services\AccesoCore;
use App\Services\EscrituraCore;

class PersonalProfileController extends Controller
{
    /** La persona con sesión abierta, con las columnas de LecturaCore::personas('personal'). */
    private function getPersonalActual(): ?object
    {
        $id = AccesoCore::personaActual('personal');
        return $id ? DB::query()->fromSub(LecturaCore::personas('personal'), 'personal')->where('id_personal', $id)->first() : null;
    }

    private static function passwordCorrecta(int $idPersona, string $password): bool
    {
        $hash = DB::table('core.usuario')->where('id_persona', $idPersona)->value('password_hash');
        return $hash && Hash::check($password, $hash);
    }

    public function show()
    {
        $p = $this->getPersonalActual();
        if (!$p) return response()->json(['error' => 'No autenticado'], 401);

        $rol = DB::query()->fromSub(LecturaCore::roles(), 'rol')->where('id_rol', $p->id_rol)->first();
return response()->json([
    'nombre'             => $p->nombre,
    'apellido_p'         => $p->apellido_p,
    'apellido_m'         => $p->apellido_m,
    'email'              => $p->email,
    'telefono'           => $p->telefono,
    'fecha_nacimiento'   => $p->fecha_nacimiento,
    'genero'             => $p->genero,
    'estado_residencia'  => $p->estado_residencia,
    'ciudad'             => $p->ciudad,
    'calle'              => $p->calle,
    'codigo_postal'      => $p->codigo_postal,
    'nombre_rol'         => $rol?->nombre ?? '—',
]);
    }

    public function update(Request $request)
    {
        $p = $this->getPersonalActual();
        if (!$p) return response()->json(['error' => 'No autenticado'], 401);

        $validator = Validator::make($request->all(), [
            'nombre'           => 'required|string|max:50',
            'apellido_p'       => 'required|string|max:50',
            'apellido_m'       => 'nullable|string|max:50',
            'email'            => 'required|email|max:100',
            'telefono'         => 'required|string|max:20',
            'fecha_nacimiento' => 'required|date',
            'genero'           => 'required|in:f,m,o',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (EscrituraCore::correoOcupado($request->email, (int) $p->id_personal)) {
            return response()->json(['errors' => ['email' => ['Ese correo ya tiene una cuenta en el sistema.']]], 422);
        }

        DB::transaction(fn () => EscrituraCore::persona('personal', (int) $p->id_personal, $request->only([
            'nombre', 'apellido_p', 'apellido_m', 'email', 'telefono', 'fecha_nacimiento', 'genero',
        ])));

        return response()->json(['message' => 'Perfil actualizado correctamente.']);
    }

    public function updatePassword(Request $request)
    {
        $p = $this->getPersonalActual();
        if (!$p) return response()->json(['error' => 'No autenticado'], 401);

        $validator = Validator::make($request->all(), [
            'contrasena_antigua'   => 'required',
            'contrasena_nueva'     => 'required|min:6',
            'contrasena_confirmar' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!self::passwordCorrecta((int) $p->id_personal, $request->contrasena_antigua)) {
            return response()->json(['error' => 'La contraseña antigua no es correcta.'], 422);
        }

        if ($request->contrasena_nueva !== $request->contrasena_confirmar) {
            return response()->json(['error' => 'La nueva contraseña y su confirmación no coinciden.'], 422);
        }

        EscrituraCore::password((int) $p->id_personal, $request->contrasena_nueva);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}