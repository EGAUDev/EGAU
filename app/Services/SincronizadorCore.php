<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Copia al esquema v2.2 cada alta o cambio que las pantallas hacen en las
 * tablas viejas de public: usuarios, sedes y roles (core.*), facturas
 * (finanzas.*) y cursos, grupos e inscripciones (academico.*).
 *
 * Las tablas viejas se siguen escribiendo porque los guardados todavía
 * validan contra ellas; las pantallas ya leen de v2.2 (ver LecturaCore).
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
            'global'      => $administrativo,
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

        // 4. Sedes y roles del personal
        if ($tabla === 'personal') {
            self::sedesDePersonal($id, $idPersona);
            self::rolesDePersonal($id, (int) $idUsuario);
        }

        // 5. Tutor del alumno
        if ($tabla === 'alumno') {
            self::tutor($id, $idPersona, isset($j['id_tutor']) ? (int) $j['id_tutor'] : null);
        }
    }

    /** Copia solo el tutor de un alumno ya migrado (para pasar los que ya existen). */
    public static function tutorDeAlumno(int $idAlumno): void
    {
        $idPersona = self::mapa('alumno', $idAlumno);
        if ($idPersona) {
            self::tutor($idAlumno, $idPersona, DB::table('alumno')->where('id_alumno', $idAlumno)->value('id_tutor'));
        }
    }

    /**
     * En v2.2 el tutor es otra persona ligada en core.alumno_tutor (sin cuenta
     * ni perfil). En migracion.mapa queda como tabla_origen 'tutor'.
     */
    private static function tutor(int $idAlumno, int $idPersonaAlumno, ?int $idTutor): void
    {
        $t = $idTutor ? DB::table('tutor')->where('id_tutor', $idTutor)->first() : null;
        if (!$t) {
            DB::table('core.alumno_tutor')->where('id_alumno', $idPersonaAlumno)->delete();
            return;
        }

        $datos = [
            'nombre'     => mb_substr(self::texto($t->nombre) ?? '-', 0, 60),
            'apellido_p' => mb_substr(self::texto($t->apellido_p) ?? '-', 0, 60),
            'apellido_m' => self::corto($t->apellido_m, 60),
            'telefono'   => self::corto($t->telefono, 20),
            'email'      => ($e = self::texto($t->email)) ? mb_strtolower($e) : null,
            'genero'     => 'N',   // v1 no guarda el género del tutor
        ];
        $idPersona = self::mapa('tutor', $idTutor);
        if ($idPersona) {
            DB::table('core.persona')->where('id', $idPersona)->update($datos);
        } else {
            $idPersona = DB::table('core.persona')->insertGetId($datos);
            self::guardarMapa('tutor', $idTutor, $idPersona);
        }
        if ($idPersona === $idPersonaAlumno) {
            return;   // v2.2 no permite que alguien sea su propio tutor
        }

        $parentesco = in_array($t->parentesco, ['madre', 'padre', 'abuelo', 'abuela', 'hermano', 'hermana', 'tutor_legal'], true)
            ? $t->parentesco : 'otro';
        DB::table('core.alumno_tutor')->where('id_alumno', $idPersonaAlumno)->where('id_tutor', '!=', $idPersona)->delete();
        DB::table('core.alumno_tutor')->updateOrInsert(
            ['id_alumno' => $idPersonaAlumno, 'id_tutor' => $idPersona],
            ['parentesco' => $parentesco, 'principal' => true, 'autoriza_recoger' => true]
        );
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

    /** core.perfil_sede = las sedes de usuariosede del personal. */
    private static function sedesDePersonal(int $idPersonal, int $idPersona): void
    {
        $deseadas = [];
        foreach (DB::table('usuariosede')->where('id_personal', $idPersonal)->get() as $us) {
            if (!self::mapa('sede', (int) $us->id_sede)) {
                self::sede((int) $us->id_sede);
            }
            $deseadas[self::mapa('sede', (int) $us->id_sede)] = $us->fecha_asignacion;
        }

        DB::table('core.perfil_sede')->where('id_persona', $idPersona)->where('tipo', 'personal')
            ->whereNotIn('id_sede', array_keys($deseadas))->delete();
        $actuales = DB::table('core.perfil_sede')->where('id_persona', $idPersona)->where('tipo', 'personal')->pluck('id_sede')->all();
        foreach ($deseadas as $idSede => $fecha) {
            if (!in_array($idSede, $actuales)) {
                DB::table('core.perfil_sede')->insert([
                    'id_persona' => $idPersona, 'tipo' => 'personal', 'id_sede' => $idSede, 'asignado_en' => $fecha ?? now(),
                ]);
            }
        }
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
            if (DB::table('core.rol')->where('id', $idRolCore)->value('global')) {
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

    // ===================== PAGOS =====================

    // vigencia de public.factura -> estado de finanzas.cargo ('pagado' sale de los pagos)
    private const ESTADO_CARGO = ['enproceso' => 'pendiente', 'expirado' => 'vencido', 'cancelado' => 'cancelado', 'pagado' => 'pagado'];

    /**
     * Copia una factura a finanzas.cargo. Una factura pagada lleva un registro
     * en finanzas.pago por el total (método 'otro': v1 no guarda cómo se pagó).
     * El cargo aún no se liga a academico.inscripcion; la factura queda en migracion.mapa.
     */
    public static function cargo(int $idFactura): void
    {
        $f = DB::table('factura')->where('id_factura', $idFactura)->first();
        if (!$f) {
            return;
        }

        $idPersona = self::mapa('alumno', (int) $f->id_alumno);
        if (!$idPersona) {
            self::persona('alumno', (int) $f->id_alumno);
            $idPersona = self::mapa('alumno', (int) $f->id_alumno);
        }
        if (!$idPersona) {
            return;
        }

        $idSedeLegacy = DB::table('alumno')->where('id_alumno', $f->id_alumno)->value('id_sede');
        $idSede = ($idSedeLegacy ? self::mapa('sede', (int) $idSedeLegacy) : null)
            ?? (int) DB::table('core.perfil')->where('id_persona', $idPersona)->where('tipo', 'alumno')->value('id_sede');

        $total = round((float) ($f->total_pago ?? 0), 2);
        $estado = self::ESTADO_CARGO[$f->vigencia] ?? 'pendiente';
        $datos = [
            'id_sede'       => $idSede,
            'id_alumno'     => $idPersona,
            'id_concepto'   => DB::table('finanzas.concepto')->where('clave', $f->id_inscripcionextra ? 'extraescolar' : 'inscripcion')->value('id'),
            'descripcion'   => trim(($f->concepto ?? '') . ($f->descripcion ? ' | ' . $f->descripcion : '')) ?: null,
            'subtotal'      => $total,
            'fecha_emision' => $f->fecha_emision,
            'fecha_limite'  => substr((string) $f->fecha_limite, 0, 10),
            'id_inscripcion' => $f->id_inscripcion ? self::mapa('inscripcion', (int) $f->id_inscripcion)
                : ($f->id_inscripcionextra ? self::mapa('inscripcionextra', (int) $f->id_inscripcionextra) : null),
        ];

        $idCargo = self::mapa('factura', $idFactura);
        $cargo = $idCargo ? DB::table('finanzas.cargo')->where('id', $idCargo)->first() : null;
        if (!$cargo) {
            $idCargo = DB::table('finanzas.cargo')->insertGetId($datos + ['estado' => 'pendiente']);
            DB::table('migracion.mapa')->where('tabla_origen', 'factura')->where('id_origen', $idFactura)->delete();
            self::guardarMapa('factura', $idFactura, $idCargo);
        } elseif ($cargo->id_sede != $idSede) {
            unset($datos['id_sede']);   // el folio es por sede; no se mueve de sede un cargo ya emitido
        }

        $pagado = (float) DB::table('finanzas.pago')->where('id_cargo', $idCargo)->sum('monto');

        if ($estado === 'pagado') {
            // Sin pagos aún: queda pendiente y el pago lo pasa a pagado (lo hace el trigger de finanzas.pago)
            DB::table('finanzas.cargo')->where('id', $idCargo)->update($datos + [
                'estado' => $pagado >= $total && $total > 0 ? 'pagado' : 'pendiente', 'cancelado_en' => null,
            ]);
            if ($total > 0 && $pagado < $total) {
                DB::table('finanzas.pago')->insert([
                    'id_cargo' => $idCargo, 'monto' => round($total - $pagado, 2), 'metodo' => 'otro',
                    'referencia' => 'factura v1 #' . $idFactura, 'fecha_pago' => now(),
                ]);
            } elseif ($total == 0) {
                DB::table('finanzas.cargo')->where('id', $idCargo)->update(['estado' => 'pagado']);
            }
            return;
        }

        DB::table('finanzas.cargo')->where('id', $idCargo)->update($datos + [
            'estado'       => $estado,
            'cancelado_en' => $estado === 'cancelado' ? ($cargo?->cancelado_en ?? now()) : null,
        ]);
    }

    /** Varias facturas a la vez (p. ej. las que se acaban de vencer). */
    public static function cargos(iterable $idsFactura): void
    {
        foreach ($idsFactura as $id) {
            self::cargo((int) $id);
        }
    }

    // ===================== CURSOS Y GRUPOS =====================

    /** Copia un curso a academico.curso (tipo regular) y su sede a academico.curso_sede. */
    public static function curso(int $idCurso): void
    {
        $c = DB::table('curso')->where('id_curso', $idCurso)->first();
        if (!$c) {
            return;
        }

        $datos = [
            'nombre'           => mb_substr(self::texto($c->nombre) ?? 'Curso ' . $idCurso, 0, 100),
            'id_nivel_minimo'  => self::nivelMinimo($c->nivel),
            'duracion_semanas' => max(1, min(32767, (int) $c->duracion_semanas)),
            'horas_totales'    => max(1, min(32767, (int) $c->horas_totales)),
            'costo_base'       => max(0, round((float) ($c->costo_base ?? 0), 2)),
            'descripcion'      => self::texto($c->descripcion),
            'requisitos'       => self::texto($c->requisitos),
            'activo'           => self::activo($c->estatus),
        ];

        $idCore = self::mapa('curso', $idCurso);
        if ($idCore && DB::table('academico.curso')->where('id', $idCore)->exists()) {
            DB::table('academico.curso')->where('id', $idCore)->update($datos);
        } else {
            $idCore = DB::table('academico.curso')->insertGetId($datos + ['clave' => 'C' . $idCurso, 'tipo' => 'regular']);
            DB::table('migracion.mapa')->where('tabla_origen', 'curso')->where('id_origen', $idCurso)->delete();
            self::guardarMapa('curso', $idCurso, $idCore);
        }

        // En v1 un curso pertenece a una sola sede
        $idSede = $c->id_sede ? self::mapa('sede', (int) $c->id_sede) : null;
        DB::table('academico.curso_sede')->where('id_curso', $idCore)
            ->when($idSede, fn ($q) => $q->where('id_sede', '!=', $idSede))->delete();
        if ($idSede) {
            DB::table('academico.curso_sede')->updateOrInsert(
                ['id_curso' => $idCore, 'id_sede' => $idSede],
                ['activo' => self::activo($c->estatus)]
            );
        }
    }

    public static function eliminarCurso(int $idCurso): void
    {
        $idCore = self::mapa('curso', $idCurso);
        if ($idCore) {
            DB::table('academico.curso')->where('id', $idCore)->delete();
        }
        DB::table('migracion.mapa')->where('tabla_origen', 'curso')->where('id_origen', $idCurso)->delete();
    }

    /**
     * Copia un grupo con sus horarios. La sede es la del curso (v1 no la guarda
     * en el grupo); el periodo es el año de fecha_inicio. Los horarios se
     * rehacen completos, como hace la pantalla de v1.
     */
    public static function grupo(int $idGrupo): void
    {
        $g = DB::table('grupo')->where('id_grupo', $idGrupo)->first();
        if (!$g) {
            return;
        }

        if (!self::mapa('curso', (int) $g->id_curso)) {
            self::curso((int) $g->id_curso);
        }
        $idCurso = self::mapa('curso', (int) $g->id_curso);
        $idProfesor = self::mapa('profesor', (int) $g->id_profesor);
        if (!$idProfesor) {
            self::persona('profesor', (int) $g->id_profesor);
            $idProfesor = self::mapa('profesor', (int) $g->id_profesor);
        }

        $idSedeLegacy = DB::table('curso')->where('id_curso', $g->id_curso)->value('id_sede')
            ?? DB::table('profesor')->where('id_profesor', $g->id_profesor)->value('id_sede');
        $idSede = $idSedeLegacy ? self::mapa('sede', (int) $idSedeLegacy) : null;
        $idSede ??= (int) DB::table('core.perfil')->where('id_persona', $idProfesor)->where('tipo', 'profesor')->value('id_sede');

        $idCore = self::mapa('grupo', $idGrupo);
        if ($idCore && !DB::table('academico.grupo')->where('id', $idCore)->exists()) {
            $idCore = null;
        }

        // El código es único por sede en v2.2; si choca con otro grupo se le agrega el id de v1
        $codigo = mb_substr(self::texto($g->codigo_grupo) ?? 'G' . $idGrupo, 0, 20);
        $choca = DB::table('academico.grupo')->where('id_sede', $idSede)->where('codigo', $codigo)
            ->when($idCore, fn ($q) => $q->where('id', '!=', $idCore))->exists();
        if ($choca) {
            $codigo = mb_substr($codigo, 0, 20 - strlen('-G' . $idGrupo)) . '-G' . $idGrupo;
        }

        $inicio = substr((string) $g->fecha_inicio, 0, 10);
        $fin = substr((string) $g->fecha_fin, 0, 10);
        if ($fin <= $inicio) {
            $fin = \Carbon\Carbon::parse($inicio)->addDay()->toDateString();
        }

        $datos = [
            'id_sede'      => $idSede,
            'id_curso'     => $idCurso,
            'id_profesor'  => $idProfesor,
            'id_periodo'   => self::periodo($inicio, $g->periodo),
            'codigo'       => $codigo,
            'nombre'       => self::corto($g->nombre, 100),
            'fecha_inicio' => $inicio,
            'fecha_fin'    => $fin,
            'cupo_maximo'  => max(1, min(32767, (int) $g->cupo_maximo)),
            'estado'       => self::activo($g->estatus) ? 'abierto' : 'cerrado',
        ];

        if ($idCore) {
            DB::table('academico.grupo')->where('id', $idCore)->update($datos);
            DB::table('academico.inscripcion')->where('id_grupo', $idCore)->where('id_sede', '!=', $idSede)->update(['id_sede' => $idSede]);
        } else {
            $idCore = DB::table('academico.grupo')->insertGetId($datos);
            DB::table('migracion.mapa')->where('tabla_origen', 'grupo')->where('id_origen', $idGrupo)->delete();
            self::guardarMapa('grupo', $idGrupo, $idCore);
        }

        DB::table('academico.horario')->where('id_grupo', $idCore)->delete();
        $vistos = [];
        foreach (DB::table('horario')->where('id_grupo', $idGrupo)->orderBy('id_horario')->get() as $h) {
            $dia = (int) $h->dia_semana === 0 ? 7 : (int) $h->dia_semana;
            $ini = substr((string) $h->hora_inicio, 0, 8);
            $fi = substr((string) $h->hora_fin, 0, 8);
            $llave = "$dia|$ini|$fi";
            if ($dia < 1 || $dia > 7 || $fi <= $ini || isset($vistos[$llave])) {
                continue;   // v1 a veces repite el mismo horario o lo deja vacío
            }
            $vistos[$llave] = true;
            self::insertarHorario($idCore, $dia, $ini, $fi, self::aula($idSede, $h->ubicacion ?? null));
        }
    }

    public static function eliminarGrupo(int $idGrupo): void
    {
        $idCore = self::mapa('grupo', $idGrupo);
        if ($idCore) {
            DB::table('academico.grupo')->where('id', $idCore)->delete();   // los horarios se borran en cascada
        }
        DB::table('migracion.mapa')->where('tabla_origen', 'grupo')->where('id_origen', $idGrupo)->delete();
    }

    /**
     * Copia una inscripción. Activa en v1 = 'activa'; dada de baja = 'baja' con
     * fecha y motivo (v2.2 los exige). Si v2.2 la rechaza por cupo lleno queda
     * en 'lista_espera', para no bloquear algo que v1 sí permitió.
     */
    public static function inscripcion(int $idInscripcion): void
    {
        $i = DB::table('inscripcion')->where('id_inscripcion', $idInscripcion)->first();
        if (!$i) {
            return;
        }

        $idAlumno = self::mapa('alumno', (int) $i->id_alumno);
        if (!$idAlumno) {
            self::persona('alumno', (int) $i->id_alumno);
            $idAlumno = self::mapa('alumno', (int) $i->id_alumno);
        }
        if (!self::mapa('grupo', (int) $i->id_grupo)) {
            self::grupo((int) $i->id_grupo);
        }
        $idGrupo = self::mapa('grupo', (int) $i->id_grupo);
        if (!$idAlumno || !$idGrupo) {
            return;
        }

        $activa = filter_var($i->estatus, FILTER_VALIDATE_BOOLEAN);
        $actual = ($id = self::mapa('inscripcion', $idInscripcion))
            ? DB::table('academico.inscripcion')->where('id', $id)->first() : null;
        $datos = [
            'id_alumno'   => $idAlumno,
            'id_grupo'    => $idGrupo,
            'id_sede'     => DB::table('academico.grupo')->where('id', $idGrupo)->value('id_sede'),
            'estado'      => $activa ? 'activa' : 'baja',
            'fecha_alta'  => $i->fecha_asignacion,
            'fecha_baja'  => $activa ? null : ($i->fecha_baja ?? $actual?->fecha_baja ?? now()),
            'motivo_baja' => $activa ? null : (self::texto($i->motivo_baja) ?? $actual?->motivo_baja ?? 'Baja registrada en v1'),
        ];

        self::guardarInscripcion('inscripcion', $idInscripcion, $datos, $activa);
    }

    // ===================== EXTRAESCOLARES =====================

    /**
     * En v2.2 una extraescolar es un curso tipo 'extraescolar' con un grupo.
     * v1 no le asigna sede ni profesor: el grupo va a la sede principal
     * (la activa con id de v1 más bajo) y sin profesor. v1 no guarda horas:
     * se toma una hora por semana.
     */
    public static function extraescolar(int $idExtra): void
    {
        $e = DB::table('extraescolar')->where('id_extraescolar', $idExtra)->first();
        if (!$e) {
            return;
        }

        $cursoDatos = [
            'nombre'           => mb_substr(self::texto($e->nombre) ?? 'Extraescolar ' . $idExtra, 0, 100),
            'tipo'             => 'extraescolar',
            'duracion_semanas' => max(1, min(32767, (int) $e->duracion_semanas)),
            'horas_totales'    => max(1, min(32767, (int) $e->duracion_semanas)),
            'costo_base'       => max(0, round((float) ($e->costo_base ?? 0), 2)),
            'descripcion'      => self::texto($e->descripcion),
            'requisitos'       => self::texto($e->requisitos),
            'activo'           => self::activo($e->estatus),
        ];

        $idCurso = self::mapa('extraescolar', $idExtra);
        if ($idCurso && DB::table('academico.curso')->where('id', $idCurso)->exists()) {
            DB::table('academico.curso')->where('id', $idCurso)->update($cursoDatos);
        } else {
            $idCurso = DB::table('academico.curso')->insertGetId($cursoDatos + ['clave' => 'E' . $idExtra]);
            DB::table('migracion.mapa')->where('tabla_origen', 'extraescolar')->where('id_origen', $idExtra)->delete();
            self::guardarMapa('extraescolar', $idExtra, $idCurso);
        }

        $idGrupo = self::mapa('extraescolar_grupo', $idExtra);
        $grupo = $idGrupo ? DB::table('academico.grupo')->where('id', $idGrupo)->first() : null;
        $idSede = $grupo->id_sede ?? self::sedePrincipal();

        DB::table('academico.curso_sede')->updateOrInsert(
            ['id_curso' => $idCurso, 'id_sede' => $idSede],
            ['activo' => self::activo($e->estatus)]
        );

        $inicio = substr((string) $e->fecha_inicio, 0, 10);
        $fin = substr((string) $e->fecha_fin, 0, 10);
        if ($fin <= $inicio) {
            $fin = \Carbon\Carbon::parse($inicio)->addDay()->toDateString();   // v2.2 pide fin posterior al inicio
        }

        $grupoDatos = [
            'id_sede'      => $idSede,
            'id_curso'     => $idCurso,
            'id_periodo'   => self::periodo($inicio),
            'fecha_inicio' => $inicio,
            'fecha_fin'    => $fin,
            'ubicacion'    => self::corto($e->ubicacion, 100),
            'cupo_maximo'  => max(1, min(32767, (int) $e->cupo_maximo)),
            'estado'       => self::activo($e->estatus) ? 'abierto' : 'cerrado',
        ];

        if ($grupo) {
            DB::table('academico.grupo')->where('id', $idGrupo)->update($grupoDatos);
        } else {
            $codigo = 'EXT-' . $idExtra;
            if (DB::table('academico.grupo')->where('id_sede', $idSede)->where('codigo', $codigo)->exists()) {
                $codigo .= '-' . substr(md5(uniqid()), 0, 4);
            }
            $idGrupo = DB::table('academico.grupo')->insertGetId($grupoDatos + ['codigo' => $codigo]);
            DB::table('migracion.mapa')->where('tabla_origen', 'extraescolar_grupo')->where('id_origen', $idExtra)->delete();
            self::guardarMapa('extraescolar_grupo', $idExtra, $idGrupo);
        }
    }

    public static function eliminarExtraescolar(int $idExtra): void
    {
        $idGrupo = self::mapa('extraescolar_grupo', $idExtra);
        if ($idGrupo) {
            DB::table('academico.grupo')->where('id', $idGrupo)->delete();
        }
        $idCurso = self::mapa('extraescolar', $idExtra);
        if ($idCurso) {
            DB::table('academico.curso')->where('id', $idCurso)->delete();
        }
        DB::table('migracion.mapa')->whereIn('tabla_origen', ['extraescolar', 'extraescolar_grupo'])->where('id_origen', $idExtra)->delete();
    }

    /** Copia una inscripción a extraescolar (status de v1) al grupo de esa extraescolar. */
    public static function inscripcionExtra(int $idInscripcion): void
    {
        $i = DB::table('inscripcionextraescolar')->where('id_inscripcionextra', $idInscripcion)->first();
        if (!$i) {
            return;
        }

        $idAlumno = self::mapa('alumno', (int) $i->id_alumno);
        if (!$idAlumno) {
            self::persona('alumno', (int) $i->id_alumno);
            $idAlumno = self::mapa('alumno', (int) $i->id_alumno);
        }
        if (!self::mapa('extraescolar_grupo', (int) $i->id_extraescolar)) {
            self::extraescolar((int) $i->id_extraescolar);
        }
        $idGrupo = self::mapa('extraescolar_grupo', (int) $i->id_extraescolar);
        if (!$idAlumno || !$idGrupo) {
            return;
        }

        $activa = filter_var($i->status, FILTER_VALIDATE_BOOLEAN);
        $actual = ($id = self::mapa('inscripcionextra', $idInscripcion))
            ? DB::table('academico.inscripcion')->where('id', $id)->first() : null;
        self::guardarInscripcion('inscripcionextra', $idInscripcion, [
            'id_alumno'   => $idAlumno,
            'id_grupo'    => $idGrupo,
            'id_sede'     => DB::table('academico.grupo')->where('id', $idGrupo)->value('id_sede'),
            'estado'      => $activa ? 'activa' : 'baja',
            'fecha_alta'  => $i->fecha_asignacion,
            // v1 no guarda cuándo ni por qué se dio de baja
            'fecha_baja'  => $activa ? null : ($actual?->fecha_baja ?? now()),
            'motivo_baja' => $activa ? null : ($actual?->motivo_baja ?? 'Baja registrada en v1'),
        ], $activa);
    }

    /** Sede donde quedan las extraescolares (v1 no les asigna sede). */
    private static function sedePrincipal(): int
    {
        return (int) DB::table('sede as s')
            ->join('migracion.mapa as m', fn ($j) => $j->on('m.id_origen', '=', 's.id_sede')->where('m.tabla_origen', 'sede'))
            ->orderByRaw('coalesce(s.estatus, true) desc')->orderBy('s.id_sede')->value('m.id_nuevo');
    }

    /**
     * Guarda en academico.inscripcion (sirve para grupos y extraescolares). Si
     * v2.2 la rechaza por cupo lleno queda en 'lista_espera', para no bloquear
     * algo que v1 sí permitió; y liga a ella los cargos de su factura.
     */
    private static function guardarInscripcion(string $tablaMapa, int $idLegacy, array $datos, bool $activa): void
    {
        $idCore = self::mapa($tablaMapa, $idLegacy);
        if ($idCore && !DB::table('academico.inscripcion')->where('id', $idCore)->exists()) {
            $idCore = null;
        }

        $guardar = function (array $d) use (&$idCore) {
            if ($idCore) {
                DB::table('academico.inscripcion')->where('id', $idCore)->update($d);
            } else {
                $idCore = DB::table('academico.inscripcion')->insertGetId($d);
            }
        };

        try {
            DB::transaction(fn () => $guardar($datos));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23514' && $activa) {          // cupo lleno en v2.2
                $guardar(['estado' => 'lista_espera'] + $datos);
            } elseif ($e->getCode() === '23505' && $activa) {    // ya hay otra activa del mismo alumno en el grupo
                $previa = $idCore ? DB::table('academico.inscripcion')->where('id', $idCore)->value('fecha_baja') : null;
                $guardar(['estado' => 'baja', 'fecha_baja' => $previa ?? now(), 'motivo_baja' => 'Inscripción repetida en v1'] + $datos);
            } else {
                throw $e;
            }
        }

        DB::table('migracion.mapa')->where('tabla_origen', $tablaMapa)->where('id_origen', $idLegacy)
            ->where('id_nuevo', '!=', $idCore)->delete();
        self::guardarMapa($tablaMapa, $idLegacy, $idCore);

        // Los cargos de esta inscripción quedan ligados a ella
        $cargos = DB::table('factura as f')
            ->join('migracion.mapa as m', fn ($j) => $j->on('m.id_origen', '=', 'f.id_factura')->where('m.tabla_origen', 'factura'))
            ->where($tablaMapa === 'inscripcion' ? 'f.id_inscripcion' : 'f.id_inscripcionextra', $idLegacy)->pluck('m.id_nuevo');
        if ($cargos->isNotEmpty()) {
            DB::table('finanzas.cargo')->whereIn('id', $cargos)->where(fn ($q) => $q->whereNull('id_inscripcion')->orWhere('id_inscripcion', '!=', $idCore))
                ->update(['id_inscripcion' => $idCore]);
        }
    }

    /** Nivel de v2.2 cuyo rating mínimo es el más alto que no pasa del puntaje que pide el curso. */
    private static function nivelMinimo($puntaje): ?int
    {
        if ($puntaje === null || $puntaje === '') {
            return null;
        }
        $id = DB::table('academico.nivel')->where('rating_minimo', '<=', (int) $puntaje)->orderByDesc('rating_minimo')->value('id');
        return $id !== null ? (int) $id : null;
    }

    /**
     * El periodo elegido en la pantalla (su clave); si no hay, el año de inicio
     * del grupo (v2.2 no permite periodos traslapados).
     */
    private static function periodo(string $fechaInicio, ?string $clave = null): int
    {
        $id = $clave !== null && trim($clave) !== ''
            ? DB::table('academico.periodo')->where('clave', trim($clave))->value('id') : null;
        if ($id) {
            return (int) $id;
        }
        $anio = (int) substr($fechaInicio, 0, 4);
        $id = DB::table('academico.periodo')->where('clave', (string) $anio)->value('id');
        if ($id) {
            return (int) $id;
        }
        $id = DB::table('academico.periodo')->whereRaw('vigencia @> ?::date', [$fechaInicio])->value('id');
        if ($id) {
            return (int) $id;
        }
        return (int) DB::table('academico.periodo')->insertGetId([
            'clave' => (string) $anio, 'nombre' => 'Año ' . $anio,
            'vigencia' => DB::raw("daterange('{$anio}-01-01', '" . ($anio + 1) . "-01-01')"),
        ]);
    }

    /** Aula de la sede con ese nombre (la "ubicación" de v1); se crea si no existe. */
    private static function aula(int $idSede, $ubicacion): ?int
    {
        $nombre = self::corto($ubicacion, 40);
        if (!$nombre) {
            return null;
        }
        $id = DB::table('core.aula')->where('id_sede', $idSede)->where('nombre', $nombre)->value('id');
        return (int) ($id ?? DB::table('core.aula')->insertGetId(['id_sede' => $idSede, 'nombre' => $nombre]));
    }

    /**
     * v2.2 no deja empalmar un aula ni a un profesor; v1 sí. Si el aula choca se
     * guarda sin aula; si choca el profesor, ese horario se queda solo en v1.
     */
    private static function insertarHorario(int $idGrupo, int $dia, string $ini, string $fin, ?int $idAula): void
    {
        foreach (array_unique([$idAula, null], SORT_REGULAR) as $aula) {
            try {
                DB::transaction(fn () => DB::table('academico.horario')->insert([
                    'id_grupo' => $idGrupo, 'id_aula' => $aula, 'dia_semana' => $dia,
                    'horas' => DB::raw("academico.rango_hora('{$ini}'::time, '{$fin}'::time)"),
                    'vigencia' => DB::raw("'empty'::daterange"),   // la pone el trigger con las fechas del grupo
                ]));
                return;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() !== '23P01') {
                    throw $e;
                }
            }
        }
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
