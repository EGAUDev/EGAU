<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\Personal;
use App\Services\SincronizadorCore;

class PersonalProfileController extends Controller
{
    private function getPersonalActual(): ?Personal
    {
        $token = session('token');
        if (!$token) return null;

        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken || !($accessToken->tokenable instanceof Personal)) return null;

        return $accessToken->tokenable;
    }

    public function show()
    {
        $p = $this->getPersonalActual();
        if (!$p) return response()->json(['error' => 'No autenticado'], 401);

        $rol = DB::table('rol')->where('id_rol', $p->id_rol)->first();
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
            'email'            => 'required|email|max:100|unique:personal,email,' . $p->id_personal . ',id_personal',
            'telefono'         => 'required|string|max:20',
            'fecha_nacimiento' => 'required|date',
            'genero'           => 'required|in:f,m,o',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (SincronizadorCore::correoOcupado($request->email, 'personal', $p->id_personal)) {
            return response()->json(['errors' => ['email' => ['Ese correo ya tiene una cuenta en el sistema.']]], 422);
        }

        $p->nombre             = $request->nombre;
        $p->apellido_p         = $request->apellido_p;
        $p->apellido_m         = $request->apellido_m;
        $p->email              = $request->email;
        $p->telefono           = $request->telefono;
        $p->fecha_nacimiento   = $request->fecha_nacimiento;
        $p->genero             = $request->genero;
        $p->fecha_modificacion = now();
        DB::transaction(function () use ($p) {
            $p->save();
            SincronizadorCore::persona('personal', $p->id_personal);
        });

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

        if (!Hash::check($request->contrasena_antigua, $p->contraseña)) {
            return response()->json(['error' => 'La contraseña antigua no es correcta.'], 422);
        }

        if ($request->contrasena_nueva !== $request->contrasena_confirmar) {
            return response()->json(['error' => 'La nueva contraseña y su confirmación no coinciden.'], 422);
        }

        $p->contraseña         = Hash::make($request->contrasena_nueva);
        $p->fecha_modificacion = now();
        DB::transaction(function () use ($p) {
            $p->save();
            SincronizadorCore::persona('personal', $p->id_personal, true);
        });

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}