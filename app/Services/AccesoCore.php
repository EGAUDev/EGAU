<?php
namespace App\Services;

use App\Models\Alumno;
use App\Models\Personal;
use App\Models\Profesor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Acceso con el esquema v2.2 (core.usuario, core.perfil, core.usuario_rol).
 *
 * Mientras el resto del sistema siga usando las tablas viejas de public, el
 * token de Sanctum se sigue emitiendo sobre el modelo viejo (Alumno, Profesor
 * o Personal). El enlace entre ambos vive en core.perfil.atributos
 * ({"tabla_legacy", "id_legacy"}) y en migracion.mapa, que llena
 * 01-migracion-auth.sql.
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

    private const MODELOS_LEGACY = [
        'alumno'   => Alumno::class,
        'profesor' => Profesor::class,
        'personal' => Personal::class,
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

    /** Modelo viejo (Alumno, Profesor o Personal) ligado a un perfil v2.2. */
    public static function modeloLegacy(object $perfil): ?Model
    {
        $atributos = json_decode($perfil->atributos ?? '{}', true) ?: [];
        $clase = self::MODELOS_LEGACY[$atributos['tabla_legacy'] ?? ''] ?? null;

        if (!$clase || !isset($atributos['id_legacy'])) {
            return null;
        }
        return $clase::find($atributos['id_legacy']);
    }

    /** Usuario y perfil v2.2 que corresponden al dueño de un token de Sanctum. */
    public static function desdeModeloLegacy(Model $modelo): ?object
    {
        $tabla = array_search(get_class($modelo), self::MODELOS_LEGACY, true);
        if ($tabla === false) {
            return null;
        }

        return DB::selectOne(
            "SELECT u.id AS id_usuario, u.estado, u.bloqueado_hasta, p.tipo, p.id_sede, p.estado AS estado_perfil
               FROM core.perfil p
               JOIN core.usuario u ON u.id_persona = p.id_persona
              WHERE p.tipo = ? AND p.atributos->>'tabla_legacy' = ? AND p.atributos->>'id_legacy' = ?",
            [$tabla, $tabla, (string) $modelo->getKey()]
        );
    }

    /**
     * Lo que la sesión necesita para pintar el dashboard, con los mismos
     * nombres que antes: permisos (banderas *_ver / *_edit), administrativo
     * (rol con alcance global) e id_sede (id de la sede en public).
     */
    public static function contexto(int $idUsuario, string $tipo, ?int $idSedeCore): array
    {
        $claves = array_column(
            DB::select('SELECT DISTINCT permiso FROM core.v_permiso_efectivo WHERE id_usuario = ?', [$idUsuario]),
            'permiso'
        );

        $permisos = null;
        if ($claves) {
            $permisos = [];
            foreach (self::PERMISOS_LEGACY as $bandera => $clave) {
                $permisos[$bandera] = in_array($clave, $claves, true);
            }
        }

        $administrativo = (bool) DB::selectOne(
            "SELECT EXISTS (
                SELECT 1 FROM core.usuario_rol ur JOIN core.rol r ON r.id = ur.id_rol AND r.activo
                 WHERE ur.id_usuario = ? AND ur.id_sede IS NULL
                   AND (ur.vigente_hasta IS NULL OR ur.vigente_hasta >= CURRENT_DATE)
             ) AS global",
            [$idUsuario]
        )->global;

        // Como antes, solo el personal trae sede en la sesión
        $idSede = null;
        if ($tipo === 'personal' && $idSedeCore !== null) {
            $idSede = DB::selectOne(
                "SELECT id_origen FROM migracion.mapa WHERE tabla_origen = 'sede' AND id_nuevo = ?",
                [$idSedeCore]
            )?->id_origen;
        }

        return [
            'permisos'       => $permisos,
            'administrativo' => $administrativo,
            'id_sede'        => $idSede,
        ];
    }
}
