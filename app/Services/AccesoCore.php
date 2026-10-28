<?php
namespace App\Services;

use App\Models\Core\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Acceso con el esquema v2.2 (core.usuario, core.perfil, core.usuario_rol).
 * El token de Sanctum se emite sobre core.usuario y se llama como el tipo con
 * el que se entró (alumno, profesor o personal). En la sesión quedan
 * id_usuario, id_persona y tipo; los ids de alumno, profesor y personal son
 * el id de core.persona.
 */
class AccesoCore
{
    // Permiso v2.2 -> bandera vieja que leen las vistas del dashboard
    private const PERMISOS_LEGACY = [
        'alumno_ver'             => 'alumno.ver',
        'alumno_edit'            => 'alumno.editar',
        'profesor_ver'           => 'profesor.ver',
        'profesor_edit'          => 'profesor.editar',
        'personal_ver'           => 'personal.ver',
        'personal_edit'          => 'personal.editar',
        'roles_ver'              => 'rol.ver',
        'roles_edit'             => 'rol.editar',
        'sedes_ver'              => 'sede.ver',
        'sedes_edit'             => 'sede.editar',
        'grupos_ver'             => 'grupo.ver',
        'grupos_edit'            => 'grupo.editar',
        'extracurriculares_ver'  => 'extraescolar.ver',
        'extracurriculares_edit' => 'extraescolar.editar',
        'estatus_ver'            => 'estatus.ver',
        'pagos_ver'              => 'cargo.ver',
        'pagos_edit'             => 'cargo.editar',
        'niveles_ver'            => 'nivel.ver',
        'niveles_edit'           => 'nivel.editar',
    ];

    public static function buscarUsuario(string $login): ?object
    {
        return DB::selectOne(
            'SELECT id, id_persona, usuario_login, password_hash, estado, bloqueado_hasta
               FROM core.usuario WHERE usuario_login = ?',
            [$login]
        );
    }

    public static function estaBloqueado(object $usuario): bool
    {
        return $usuario->bloqueado_hasta !== null && now()->lt($usuario->bloqueado_hasta);
    }

    /** Un hash que no sea bcrypt (cuenta sin contraseña) cuenta como contraseña incorrecta. */
    public static function passwordValida(string $password, string $hash): bool
    {
        try {
            return Hash::check($password, $hash);
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /** Registra el intento en core.intento_login y lleva la cuenta de fallos (5 = bloqueo de 15 min). */
    public static function registrarIntento(string $login, bool $exitoso, ?string $ip, ?string $motivo = null): void
    {
        try {
            DB::select('SELECT core.fn_registrar_intento(?::citext, ?::boolean, ?::inet, ?)', [
                $login, $exitoso ? 'true' : 'false', $ip, $motivo,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Perfil activo con el que entra la persona según la pestaña del login:
     * alumno -> perfil alumno; personal -> perfil personal y, si no, profesor.
     */
    public static function perfilParaLogin(int $idPersona, string $tipoSolicitado): ?object
    {
        $tipos = $tipoSolicitado === 'alumno' ? ['alumno'] : ['personal', 'profesor'];

        $perfiles = DB::table('core.perfil')
            ->where('id_persona', $idPersona)
            ->where('estado', 'activo')
            ->whereIn('tipo', $tipos)
            ->get(['tipo', 'id_sede', 'atributos']);

        foreach ($tipos as $tipo) {
            foreach ($perfiles as $perfil) {
                if ($perfil->tipo === $tipo) {
                    return $perfil;
                }
            }
        }
        return null;
    }

    /** Cuenta y perfil del dueño de un token (el nombre del token es el tipo con el que entró). */
    public static function desdeToken(Usuario $usuario, string $tipo): ?object
    {
        return DB::selectOne(
            "SELECT u.id AS id_usuario, u.id_persona, u.estado, p.tipo, p.id_sede, p.estado AS estado_perfil
               FROM core.usuario u
               JOIN core.perfil p ON p.id_persona = u.id_persona AND p.tipo = ?
              WHERE u.id = ?",
            [$tipo, $usuario->id]
        );
    }

    /**
     * Persona con la sesión abierta, si entró con ese tipo. Las rutas web la
     * toman de la sesión; las de api (auth:sanctum), del token.
     */
    public static function personaActual(string $tipo, ?Request $request = null): ?int
    {
        if (session('tipo') === $tipo && session('id_persona')) {
            return (int) session('id_persona');
        }
        $usuario = $request?->user();
        if ($usuario instanceof Usuario && $usuario->tipoSesion() === $tipo) {
            return (int) $usuario->id_persona;
        }
        return null;
    }

    /** Claves de core.permiso que hoy le dan sus roles vigentes (ej. "alumno.ver"). */
    public static function permisos(int $idUsuario): array
    {
        return array_column(
            DB::select('SELECT DISTINCT permiso FROM core.v_permiso_efectivo WHERE id_usuario = ?', [$idUsuario]),
            'permiso'
        );
    }

    /** Tiene algún rol vigente sin sede (alcance global): es lo que la app llama "administrativo". */
    public static function esGlobal(int $idUsuario): bool
    {
        return (bool) DB::selectOne(
            "SELECT EXISTS (
                SELECT 1 FROM core.usuario_rol ur JOIN core.rol r ON r.id = ur.id_rol AND r.activo
                 WHERE ur.id_usuario = ? AND ur.id_sede IS NULL
                   AND (ur.vigente_hasta IS NULL OR ur.vigente_hasta >= CURRENT_DATE)
             ) AS global",
            [$idUsuario]
        )->global;
    }

    /**
     * Lo que la sesión necesita para pintar el dashboard, con los mismos
     * nombres que antes: permisos (banderas *_ver / *_edit), administrativo
     * (rol con alcance global) e id_sede (del perfil del personal).
     */
    public static function contexto(int $idUsuario, string $tipo, ?int $idSedeCore): array
    {
        $claves = self::permisos($idUsuario);

        $permisos = null;
        if ($claves) {
            $permisos = [];
            foreach (self::PERMISOS_LEGACY as $bandera => $clave) {
                $permisos[$bandera] = in_array($clave, $claves, true);
            }
        }

        $administrativo = self::esGlobal($idUsuario);

        // Como antes, solo el personal trae sede en la sesión
        $idSede = $tipo === 'personal' ? $idSedeCore : null;

        return [
            'permisos'       => $permisos,
            'administrativo' => $administrativo,
            'id_sede'        => $idSede,
        ];
    }
}
