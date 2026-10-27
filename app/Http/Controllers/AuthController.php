<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AccesoCore;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('logIn');
    }
public function login(Request $request)
{
    $request->validate([
        'username' => 'required|email',
        'password' => 'required|min:6',
        'tipo' => 'required|in:alumno,personal',
    ], [
        'username.required' => 'El correo es obligatorio.',
        'username.email' => 'Ingresa un correo electrónico válido.',
        'password.required' => 'La contraseña es obligatoria.',
        'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
    ]);

    // v2.2: la cuenta vive en core.usuario (login = correo en minúsculas)
    $login = mb_strtolower(trim($request->input('username')));
    $password = $request->input('password');
    $tipo = $request->input('tipo');
    $ip = $request->ip();
    $credencialesInvalidas = fn () => response()->json(['message' => 'Correo o contraseña incorrectos.'], 401);

    $cuenta = AccesoCore::buscarUsuario($login);

    if (!$cuenta) {
        AccesoCore::registrarIntento($login, false, $ip, 'no_existe');
        return $credencialesInvalidas();
    }

    if (AccesoCore::estaBloqueado($cuenta)) {
        AccesoCore::registrarIntento($login, false, $ip, 'bloqueado');
        return response()->json(['message' => 'Cuenta bloqueada por varios intentos fallidos. Intenta de nuevo en 15 minutos.'], 423);
    }

    if (!AccesoCore::passwordValida($password, $cuenta->password_hash)) {
        AccesoCore::registrarIntento($login, false, $ip, 'contrasena');
        return $credencialesInvalidas();
    }

    if ($cuenta->estado !== 'activo') {
        AccesoCore::registrarIntento($login, false, $ip, 'cuenta_' . $cuenta->estado);
        return $credencialesInvalidas();
    }

    $perfil = AccesoCore::perfilParaLogin($cuenta->id_persona, $tipo);
    // El resto del sistema aún usa las tablas viejas: el token se emite sobre ese registro
    $usuario = $perfil ? AccesoCore::modeloLegacy($perfil) : null;

    if (!$usuario) {
        AccesoCore::registrarIntento($login, false, $ip, $perfil ? 'sin_registro_legacy' : 'sin_perfil_' . $tipo);
        return $credencialesInvalidas();
    }

    AccesoCore::registrarIntento($login, true, $ip);

    $tipo = $perfil->tipo;
    $token = $usuario->createToken('api-token', expiresAt: now()->addMinutes(config('sanctum.expiration')))->plainTextToken;
    $contexto = AccesoCore::contexto($cuenta->id, $tipo, $perfil->id_sede);

    return response()->json([
        'token' => $token,
        'tipo' => $tipo,
        'usuario' => $usuario,
        'permisos' => $contexto['permisos'],
        'administrativo' => $contexto['administrativo'],
        'id_sede' => $contexto['id_sede'],
    ]);
}

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}