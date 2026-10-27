<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Copia al esquema v2.2 (core.*) cada alta o cambio de usuarios, sedes y
 * roles que las pantallas hacen en las tablas viejas de public.
 *
 * Las tablas viejas se siguen escribiendo porque grupos, pagos, niveles y
 * extraescolares todavía apuntan a sus ids; v2.2 es lo que usa el login.
 * Hace por registro lo mismo que 01-migracion-auth.sql hace para todos,
 * así que ya no hay que volver a correr ese script tras cada cambio.
 * Llamar dentro de la misma transacción que el cambio en public.
 */
class SincronizadorCore
{
    // Columna de public.permiso -> claves de core.permiso
    private const EQUIVALENCIAS = [
        'alumno_ver'             => ['alumno.ver'],
        'alumno_edit'            => ['alumno.editar'],
        'profesor_ver'           => ['profesor.ver'],
        'profesor_edit'          => ['profesor.editar'],
        'personal_ver'           => ['personal.ver'],
        'personal_edit'          => ['personal.editar'],
        'roles_ver'              => ['rol.ver'],
        'roles_edit'             => ['rol.editar'],
        'sedes_ver'              => ['sede.ver'],
        'sedes_edit'             => ['sede.editar'],
        'grupos_ver'             => ['grupo.ver', 'curso.ver'],
        'grupos_edit'            => ['grupo.editar', 'curso.editar', 'inscripcion.editar'],
        'extracurriculares_ver'  => ['extraescolar.ver'],
        'extracurriculares_edit' => ['extraescolar.editar'],
        'estatus_ver'            => ['estatus.ver'],
        'pagos_ver'              => ['cargo.ver'],
        'pagos_edit'             => ['cargo.editar'],
        'niveles_ver'            => ['nivel.ver'],
        'niveles_edit'           => ['nivel.editar'],
    ];

    private const PREFIJO_CODIGO = ['alumno' => 'A', 'profesor' => 'P', 'personal' => 'E'];

    // ===================== SEDES =====================

    public static function sede(int $idSede): void
    {
        $fila = DB::table('sede')->where('id_sede', $idSede)->first();
        if (!$fila) {
            return;
        }
        $j = (array) $fila;

        $email = self::texto($j['email'] ?? null);
        $email = $email ? mb_strtolower($email) : null;
        $idCore = self::mapa('sede', $idSede);

        if ($email && DB::table('core.sede')->where('email', $email)->when($idCore, fn ($q) => $q->where('id', '!=', $idCore))->exists()) {
            $email = null;   // core.sede.email es único
        }

        $datos = [
            'nombre'        => mb_substr(self::texto($j['nombre'] ?? null) ?? 'Sede ' . $idSede, 0, 80),
            'calle'         => self::corto($j['calle'] ?? null, 120),
            'ciudad'        => self::corto($j['ciudad'] ?? null, 80),
            'estado_dir'    => self::corto($j['estado_residencia'] ?? $j['estado'] ?? null, 50),
            'codigo_postal' => self::codigoPostal($j['codigo_postal'] ?? null),
            'telefono'      => self::corto($j['telefono'] ?? null, 20),
            'email'         => $email,
            'activa'        => self::activo($j['estatus'] ?? null),
        ];

        if ($idCore) {
            DB::table('core.sede')->where('id', $idCore)->update($datos);
            return;
        }

        $idCore = DB::table('core.sede')->insertGetId(['clave' => 'S' . $idSede] + $datos);
        self::guardarMapa('sede', $idSede, $idCore);
    }

    // ===================== ROLES =====================

    public static function rol(int $idRol): void
    {
        $rol = DB::table('rol')->where('id_rol', $idRol)->first();
        if (!$rol) {
            return;
        }
        $jr = (array) $rol;
        $administrativo = (bool) ($jr['administrativo'] ?? false);

        $datos = [
            'nombre'      => mb_substr(self::texto($jr['nombre'] ?? null) ?? 'Rol ' . $idRol, 0, 80),
            'descripcion' => $jr['descripcion'] ?? null,
            'activo'      => self::activo($jr['estatus'] ?? null),
        ];

        $idCore = self::mapa('rol', $idRol);
        if ($idCore) {
            DB::table('core.rol')->where('id', $idCore)->update($datos);
        } else {
            $idCore = DB::table('core.rol')->insertGetId(['clave' => 'legacy_' . $idRol, 'es_sistema' => false] + $datos);
            self::guardarMapa('rol', $idRol, $idCore);
        }

        // Permisos: se reconstruyen desde la fila de public.permiso
        $permiso = (array) (DB::table('permiso')->where('id_permiso', $jr['id_permiso'] ?? 0)->first() ?? []);
        $claves = [];
        foreach (self::EQUIVALENCIAS as $columna => $destino) {
            $valor = $permiso[$columna] ?? ($columna === 'alumno_edit' ? ($permiso['alumnno_edit'] ?? false) : false);
            if (filter_var($valor, FILTER_VALIDATE_BOOLEAN)) {
                array_push($claves, ...$destino);
                if ($columna === 'alumno_edit' && $administrativo) {
                    $claves[] = 'alumno.eliminar';   // antes: borrar = editar + rol administrativo
                }
            }
        }

        DB::table('core.rol_permiso')->where('id_rol', $idCore)->delete();
        $idsPermiso = DB::table('core.permiso')->whereIn('clave', array_unique($claves))->pluck('id');
        DB::table('core.rol_permiso')->insert($idsPermiso->map(fn ($id) => ['id_rol' => $idCore, 'id_permiso' => $id])->all());

        // Si cambió "administrativo", cambia el alcance (global o por sede) de quien tenga el rol
        foreach (DB::table('personal')->where('id_rol', $idRol)->pluck('id_personal') as $idPersonal) {
            $idPersona = self::mapa('personal', $idPersonal);
            $idUsuario = $idPersona ? DB::table('core.usuario')->where('id_persona', $idPersona)->value('id') : null;
            if ($idUsuario) {
                self::rolesDePersonal((int) $idPersonal, (int) $idUsuario);
            }
        }
    }

    public static function eliminarRol(int $idRol): void
    {
        $idCore = self::mapa('rol', $idRol);
        if (!$idCore) {
            return;
        }
        DB::table('core.usuario_rol')->where('id_rol', $idCore)->delete();
        DB::table('core.rol')->where('id', $idCore)->delete();   // rol_permiso se borra en cascada
        DB::table('migracion.mapa')->where('tabla_origen', 'rol')->where('id_origen', $idRol)->delete();
    }

    // ===================== PERSONAS =====================

    /**
     * Crea o actualiza persona, perfil, usuario y roles de un registro de
     * public.alumno / profesor / personal. $conPassword copia la contraseña
     * (alta o cambio de contraseña); si no, se deja la que ya tiene la cuenta.
     */
    public static function persona(string $tabla, int $id, bool $conPassword = false): void
    {
        $fila = DB::table($tabla)->where("id_{$tabla}", $id)->first();
        if (!$fila) {
            return;
        }
        $j = (array) $fila;
        $email = self::texto($j['email'] ?? null);
        $email = $email ? mb_strtolower($email) : null;

        // 1. Persona: la ya ligada a este registro o, si el correo ya tiene cuenta, la de esa cuenta
        $idPersona = self::mapa($tabla, $id)
            ?? ($email ? DB::table('core.usuario')->where('usuario_login', $email)->value('id_persona') : null);

        $datos = [
            'nombre'           => mb_substr(self::texto($j['nombre'] ?? null) ?? '-', 0, 60),
            'apellido_p'       => mb_substr(self::texto($j['apellido_p'] ?? null) ?? '-', 0, 60),
            'apellido_m'       => self::corto($j['apellido_m'] ?? null, 60),
            'genero'           => in_array(strtoupper((string) ($j['genero'] ?? '')), ['F', 'M', 'O'], true) ? strtoupper($j['genero']) : 'N',
            'fecha_nacimiento' => self::fechaNacimiento($j['fecha_nacimiento'] ?? null),
            'telefono'         => self::corto($j['telefono'] ?? null, 20),
            'email'            => $email,
            'calle'            => self::corto($j['calle'] ?? null, 120),
            'ciudad'           => self::corto($j['ciudad'] ?? null, 80),
            'estado_dir'       => self::corto($j['estado_residencia'] ?? $j['estado'] ?? null, 50),
            'codigo_postal'    => self::codigoPostal($j['codigo_postal'] ?? null),
        ];
        if (array_key_exists('puntaje', $j)) {
            $datos['rating'] = max(0, min(4000, (int) ($j['puntaje'] ?? 0)));
        }

        if ($idPersona) {
            DB::table('core.persona')->where('id', $idPersona)->update($datos);
        } else {
            $idPersona = DB::table('core.persona')->insertGetId($datos);
        }
        self::guardarMapa($tabla, $id, $idPersona);

        // 2. Perfil
        $activo = self::activo($j['estatus'] ?? null);
        $perfil = [
            'id_sede' => self::sedeDePerfil($tabla, $id, $j, $idPersona),
            'puesto'  => $tabla === 'personal'
                ? mb_substr(DB::table('rol')->where('id_rol', $j['id_rol'] ?? 0)->value('nombre') ?? 'Personal', 0, 80)
                : null,
            'estado'  => $activo ? 'activo' : 'inactivo',
        ];

        $existe = DB::table('core.perfil')->where('id_persona', $idPersona)->where('tipo', $tabla)->exists();
        if ($existe) {
            DB::table('core.perfil')->where('id_persona', $idPersona)->where('tipo', $tabla)->update($perfil);
        } else {
            DB::table('core.perfil')->insert($perfil + [
                'id_persona'   => $idPersona,
                'tipo'         => $tabla,
                'codigo'       => self::PREFIJO_CODIGO[$tabla] . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                'fecha_inicio' => now()->toDateString(),
                'atributos'    => json_encode(['tabla_legacy' => $tabla, 'id_legacy' => $id]),
            ]);
        }

        // 3. Usuario (login = correo)
        $hash = self::texto($j['contraseña'] ?? null) ?? '!sin-contrasena';
        $usuario = DB::table('core.usuario')->where('id_persona', $idPersona)->first();

        if (!$usuario) {
            $idUsuario = DB::table('core.usuario')->insertGetId([
                'id_persona'    => $idPersona,
                'usuario_login' => $email ?? mb_strtolower(self::PREFIJO_CODIGO[$tabla] . str_pad((string) $id, 6, '0', STR_PAD_LEFT)),
                'password_hash' => $hash,
            ]);
        } else {
            $idUsuario = $usuario->id;
            $cambios = [];
            if ($conPassword && $usuario->password_hash !== $hash) {
                $cambios['password_hash'] = $hash;
                $cambios['password_actualizado_en'] = now();
            }
            if ($email && mb_strtolower($usuario->usuario_login) !== $email
                && !DB::table('core.usuario')->where('usuario_login', $email)->exists()) {
                $cambios['usuario_login'] = $email;
            }
            if ($cambios) {
                DB::table('core.usuario')->where('id', $idUsuario)->update($cambios);
            }
        }

        // Activo si tiene al menos un perfil activo (una cuenta suspendida se queda así)
        $algunoActivo = DB::table('core.perfil')->where('id_persona', $idPersona)->where('estado', 'activo')->exists();
        DB::table('core.usuario')->where('id', $idUsuario)->where('estado', '!=', 'suspendido')
            ->update(['estado' => $algunoActivo ? 'activo' : 'inactivo']);

        // 4. Roles del personal
        if ($tabla === 'personal') {
            self::rolesDePersonal($id, (int) $idUsuario);
        }
    }

    /**
     * Quita de v2.2 un registro que se borró de public. Si la persona ya no
     * tiene ningún otro perfil, se borra también su cuenta y la persona.
     */
    public static function eliminarPersona(string $tabla, int $id): void
    {
        $idPersona = self::mapa($tabla, $id);
        if (!$idPersona) {
            return;
        }

        DB::table('core.perfil')->where('id_persona', $idPersona)->where('tipo', $tabla)
            ->whereRaw("atributos->>'id_legacy' = ?", [(string) $id])->delete();
        DB::table('migracion.mapa')->where('tabla_origen', $tabla)->where('id_origen', $id)->delete();

        $idUsuario = DB::table('core.usuario')->where('id_persona', $idPersona)->value('id');

        if (DB::table('core.perfil')->where('id_persona', $idPersona)->exists()) {
            // Sigue siendo alumno/profesor/personal por otro registro: solo se ajusta la cuenta
            if ($idUsuario && $tabla === 'personal') {
                $legacy = DB::table('core.rol')->where('clave', 'like', 'legacy\_%')->pluck('id');
                DB::table('core.usuario_rol')->where('id_usuario', $idUsuario)->whereIn('id_rol', $legacy)->delete();
            }
            if ($idUsuario) {
                $algunoActivo = DB::table('core.perfil')->where('id_persona', $idPersona)->where('estado', 'activo')->exists();
                DB::table('core.usuario')->where('id', $idUsuario)->where('estado', '!=', 'suspendido')
                    ->update(['estado' => $algunoActivo ? 'activo' : 'inactivo']);
            }
            return;
        }

        if ($idUsuario) {
            DB::table('core.usuario_rol')->where('otorgado_por', $idUsuario)->update(['otorgado_por' => null]);
            DB::table('core.usuario_rol')->where('id_usuario', $idUsuario)->delete();
            DB::table('core.usuario')->where('id', $idUsuario)->delete();
        }
        DB::table('core.persona')->where('id', $idPersona)->delete();
    }

    /** Quita de v2.2 una sede que se borró de public. */
    public static function eliminarSede(int $idSede): void
    {
        $idCore = self::mapa('sede', $idSede);
        if (!$idCore) {
            return;
        }

        // Perfiles que tenían esta sede solo como sede de respaldo pasan a otra sede
        $otra = DB::table('migracion.mapa as m')->join('core.sede as s', 's.id', '=', 'm.id_nuevo')
            ->where('m.tabla_origen', 'sede')->where('m.id_nuevo', '!=', $idCore)
            ->orderByDesc('s.activa')->orderBy('m.id_origen')->value('m.id_nuevo');
        if ($otra) {
            DB::table('core.perfil')->where('id_sede', $idCore)->update(['id_sede' => $otra]);
        }

        DB::table('core.perfil_sede')->where('id_sede', $idCore)->delete();
        DB::table('core.usuario_rol')->where('id_sede', $idCore)->delete();
        DB::table('core.sede')->where('id', $idCore)->delete();
        DB::table('migracion.mapa')->where('tabla_origen', 'sede')->where('id_origen', $idSede)->delete();
    }

    /** Rol administrativo -> asignación global; si no, una por sede de usuariosede (o la del perfil). */
    private static function rolesDePersonal(int $idPersonal, int $idUsuario): void
    {
        $idRolLegacy = DB::table('personal')->where('id_personal', $idPersonal)->value('id_rol');
        $rol = $idRolLegacy ? DB::table('rol')->where('id_rol', $idRolLegacy)->first() : null;

        if ($rol && !self::mapa('rol', (int) $idRolLegacy)) {
            self::rol((int) $idRolLegacy);   // rol que aún no existía en v2.2
        }
        $idRolCore = $rol ? self::mapa('rol', (int) $idRolLegacy) : null;

        $deseadas = [];
        if ($idRolCore) {
            if ((bool) ((array) $rol)['administrativo']) {
                $deseadas[] = null;
            } else {
                $sedes = DB::table('usuariosede')->where('id_personal', $idPersonal)->pluck('id_sede')
                    ->map(fn ($s) => self::mapa('sede', (int) $s))->filter()->unique()->values()->all();
                if (!$sedes) {
                    $sedes = [DB::table('core.perfil')
                        ->where('id_persona', self::mapa('personal', $idPersonal))->where('tipo', 'personal')->value('id_sede')];
                }
                $deseadas = $sedes;
            }
        }

        $legacy = DB::table('core.rol')->where('clave', 'like', 'legacy\_%')->pluck('id');
        $actuales = DB::table('core.usuario_rol')->where('id_usuario', $idUsuario)->whereIn('id_rol', $legacy)->get();

        foreach ($actuales as $ur) {
            if ($ur->id_rol != $idRolCore || !in_array($ur->id_sede, $deseadas)) {
                DB::table('core.usuario_rol')->where('id', $ur->id)->delete();
            }
        }
        foreach ($deseadas as $idSede) {
            $yaEsta = DB::table('core.usuario_rol')
                ->where('id_usuario', $idUsuario)->where('id_rol', $idRolCore)
                ->when($idSede === null, fn ($q) => $q->whereNull('id_sede'), fn ($q) => $q->where('id_sede', $idSede))
                ->exists();
            if (!$yaEsta) {
                DB::table('core.usuario_rol')->insert(['id_usuario' => $idUsuario, 'id_rol' => $idRolCore, 'id_sede' => $idSede]);
            }
        }
    }

    private static function sedeDePerfil(string $tabla, int $id, array $j, int $idPersona): int
    {
        $idSede = null;
        if ($tabla === 'personal') {
            $legacy = DB::table('usuariosede')->where('id_personal', $id)
                ->orderByRaw('fecha_asignacion NULLS LAST')->orderBy('id_sede')->value('id_sede');
            $idSede = $legacy ? self::mapa('sede', (int) $legacy) : null;
            $idSede ??= DB::table('core.perfil')->where('id_persona', $idPersona)->where('tipo', 'personal')->value('id_sede');
        } elseif (!empty($j['id_sede'])) {
            if (!self::mapa('sede', (int) $j['id_sede'])) {
                self::sede((int) $j['id_sede']);
            }
            $idSede = self::mapa('sede', (int) $j['id_sede']);
        }

        // Sede de respaldo: la primera sede activa migrada
        return $idSede ?? (int) DB::table('migracion.mapa as m')
            ->join('core.sede as s', 's.id', '=', 'm.id_nuevo')
            ->where('m.tabla_origen', 'sede')
            ->orderByDesc('s.activa')->orderBy('m.id_origen')
            ->value('m.id_nuevo');
    }

    /** ¿El correo ya es el login de otra persona? (en v2.2 el login es único en todo el sistema) */
    public static function correoOcupado(string $email, ?string $tabla = null, ?int $id = null): bool
    {
        $idPersona = $tabla && $id ? self::mapa($tabla, $id) : null;
        return DB::table('core.usuario')
            ->where('usuario_login', mb_strtolower(trim($email)))
            ->when($idPersona, fn ($q) => $q->where('id_persona', '!=', $idPersona))
            ->exists();
    }

    // ===================== AYUDAS =====================

    private static function mapa(string $tabla, int $id): ?int
    {
        $idNuevo = DB::table('migracion.mapa')->where('tabla_origen', $tabla)->where('id_origen', $id)->value('id_nuevo');
        return $idNuevo !== null ? (int) $idNuevo : null;
    }

    private static function guardarMapa(string $tabla, int $id, int $idNuevo): void
    {
        DB::table('migracion.mapa')->insertOrIgnore(['tabla_origen' => $tabla, 'id_origen' => $id, 'id_nuevo' => $idNuevo]);
    }

    private static function texto($valor): ?string
    {
        $valor = trim((string) $valor);
        return $valor === '' ? null : $valor;
    }

    private static function corto($valor, int $max): ?string
    {
        $valor = self::texto($valor);
        return $valor === null ? null : mb_substr($valor, 0, $max);
    }

    private static function activo($estatus): bool
    {
        return $estatus === null ? true : filter_var($estatus, FILTER_VALIDATE_BOOLEAN);
    }

    private static function codigoPostal($valor): ?string
    {
        $valor = trim((string) $valor);
        return preg_match('/^\d{1,5}$/', $valor) ? str_pad($valor, 5, '0', STR_PAD_LEFT) : null;
    }

    private static function fechaNacimiento($valor): ?string
    {
        if (!$valor) {
            return null;
        }
        try {
            $fecha = \Carbon\Carbon::parse($valor);
        } catch (\Throwable $e) {
            return null;
        }
        return $fecha->year > 1900 && $fecha->lte(now()) ? $fecha->toDateString() : null;
    }
}
