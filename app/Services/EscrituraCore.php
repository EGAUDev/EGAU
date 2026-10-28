<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Guardados directo en v2.2 (core.*, academico.*, finanzas.*).
 *
 * Reemplaza a las tablas viejas de public: las pantallas mandan los mismos
 * campos que antes y aquí se pasan a las columnas de v2.2. Los ids son los
 * de v2.2 (alumno, profesor y personal = id de core.persona).
 * Llamar dentro de una transacción.
 */
class EscrituraCore
{
    // Bandera de la pantalla de roles -> claves de core.permiso
    private const PERMISOS_DE_BANDERA = [
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

    private const PARENTESCOS = ['madre', 'padre', 'abuelo', 'abuela', 'hermano', 'hermana', 'tutor_legal'];

    // ===================== PERSONAS =====================

    /**
     * Alta o cambio de un alumno, profesor o personal. $datos trae los campos
     * del formulario (solo los que mandó): nombre, apellido_p, apellido_m,
     * genero, fecha_nacimiento, telefono, email, calle, ciudad,
     * estado_residencia, codigo_postal, puntaje, estatus, id_sede, id_rol.
     * $password (en claro) solo en el alta o al cambiarla. Regresa el id.
     */
    public static function persona(string $tipo, ?int $idPersona, array $datos, ?string $password = null): int
    {
        $persona = [];
        $mapa = [
            'nombre' => ['nombre', 60], 'apellido_p' => ['apellido_p', 60], 'apellido_m' => ['apellido_m', 60],
            'telefono' => ['telefono', 20], 'calle' => ['calle', 120], 'ciudad' => ['ciudad', 80],
            'estado_residencia' => ['estado_dir', 50],
        ];
        foreach ($mapa as $campo => [$columna, $max]) {
            if (array_key_exists($campo, $datos)) {
                $persona[$columna] = self::corto($datos[$campo], $max);
            }
        }
        foreach (['nombre', 'apellido_p'] as $obligatorio) {
            if (array_key_exists($obligatorio, $persona) && $persona[$obligatorio] === null) {
                $persona[$obligatorio] = '-';
            }
        }
        if (array_key_exists('email', $datos)) {
            $persona['email'] = ($e = self::texto($datos['email'])) ? mb_strtolower($e) : null;
        }
        if (array_key_exists('genero', $datos)) {
            $g = strtoupper((string) $datos['genero']);
            $persona['genero'] = in_array($g, ['F', 'M', 'O'], true) ? $g : 'N';
        }
        if (array_key_exists('fecha_nacimiento', $datos)) {
            $persona['fecha_nacimiento'] = self::fechaNacimiento($datos['fecha_nacimiento']);
        }
        if (array_key_exists('codigo_postal', $datos)) {
            $persona['codigo_postal'] = self::codigoPostal($datos['codigo_postal']);
        }

        if ($idPersona) {
            if ($persona) {
                DB::table('core.persona')->where('id', $idPersona)->update($persona);
            }
        } else {
            $idPersona = DB::table('core.persona')->insertGetId($persona + ['genero' => 'N']);
        }

        if (array_key_exists('puntaje', $datos) && $tipo !== 'personal') {
            self::puntaje($idPersona, (int) ($datos['puntaje'] ?? 0), 'Cambio desde la ficha');
        }

        // Perfil
        $perfil = [];
        if (array_key_exists('estatus', $datos)) {
            $perfil['estado'] = self::activo($datos['estatus']) ? 'activo' : 'inactivo';
        }
        if ($tipo !== 'personal' && !empty($datos['id_sede'])) {
            $perfil['id_sede'] = (int) $datos['id_sede'];
        }
        if ($tipo === 'personal' && !empty($datos['id_rol'])) {
            $perfil['puesto'] = mb_substr(DB::table('core.rol')->where('id', $datos['id_rol'])->value('nombre') ?? 'Personal', 0, 80);
        }

        $existe = DB::table('core.perfil')->where('id_persona', $idPersona)->where('tipo', $tipo)->exists();
        if ($existe) {
            if ($perfil) {
                DB::table('core.perfil')->where('id_persona', $idPersona)->where('tipo', $tipo)->update($perfil);
            }
        } else {
            DB::table('core.perfil')->insert($perfil + [
                'id_persona'   => $idPersona,
                'tipo'         => $tipo,
                'id_sede'      => $perfil['id_sede'] ?? (!empty($datos['id_sede']) ? (int) $datos['id_sede'] : self::sedePrincipal()),
                'puesto'       => $perfil['puesto'] ?? ($tipo === 'personal' ? 'Personal' : null),
                'codigo'       => self::PREFIJO_CODIGO[$tipo] . str_pad((string) $idPersona, 6, '0', STR_PAD_LEFT),
                'fecha_inicio' => now()->toDateString(),
            ]);
        }

        // Cuenta (login = correo)
        $usuario = DB::table('core.usuario')->where('id_persona', $idPersona)->first();
        $email = $persona['email'] ?? DB::table('core.persona')->where('id', $idPersona)->value('email');
        if (!$usuario) {
            $idUsuario = DB::table('core.usuario')->insertGetId([
                'id_persona'    => $idPersona,
                'usuario_login' => $email ?? mb_strtolower(self::PREFIJO_CODIGO[$tipo] . str_pad((string) $idPersona, 6, '0', STR_PAD_LEFT)),
                'password_hash' => $password !== null ? bcrypt($password) : '!sin-contrasena',
            ]);
        } else {
            $idUsuario = (int) $usuario->id;
            $cambios = [];
            if ($password !== null) {
                $cambios['password_hash'] = bcrypt($password);
                $cambios['password_actualizado_en'] = now();
            }
            if (array_key_exists('email', $persona) && $email && mb_strtolower($usuario->usuario_login) !== $email) {
                $cambios['usuario_login'] = $email;
            }
            if ($cambios) {
                DB::table('core.usuario')->where('id', $idUsuario)->update($cambios);
            }
        }
        self::estadoCuenta($idPersona);

        if ($tipo === 'personal') {
            if (!empty($datos['id_sede'])) {
                self::sedePersonal($idPersona, (int) $datos['id_sede']);
            }
            if (array_key_exists('id_rol', $datos)) {
                self::rolPersonal($idUsuario, $datos['id_rol'] ? (int) $datos['id_rol'] : null);
            }
            self::alcanceRoles($idUsuario);
        }

        return $idPersona;
    }

    /** Cambia la contraseña de la cuenta de una persona. */
    public static function password(int $idPersona, string $password): void
    {
        DB::table('core.usuario')->where('id_persona', $idPersona)
            ->update(['password_hash' => bcrypt($password), 'password_actualizado_en' => now()]);
    }

    /** Puntaje (rating): queda en academico.rating_hist y el trigger lo aplica a la persona. */
    public static function puntaje(int $idPersona, int $nuevo, string $motivo): void
    {
        $nuevo = max(0, min(4000, $nuevo));
        $actual = (int) DB::table('core.persona')->where('id', $idPersona)->value('rating');
        if ($actual === $nuevo) {
            return;
        }
        DB::table('academico.rating_hist')->insert([
            'id_persona' => $idPersona, 'rating_ant' => $actual, 'rating_nvo' => $nuevo,
            'motivo' => $motivo, 'registrado_por' => session('id_usuario') ?: null,
        ]);
    }

    /**
     * Tutor de un alumno: otra persona sin cuenta, ligada en core.alumno_tutor.
     * $datos: nombre, apellido_p, apellido_m, parentesco, telefono, email.
     */
    public static function tutor(int $idAlumno, array $datos): int
    {
        $idTutor = DB::table('core.persona')->insertGetId([
            'nombre'     => self::corto($datos['nombre'] ?? null, 60) ?? '-',
            'apellido_p' => self::corto($datos['apellido_p'] ?? null, 60) ?? '-',
            'apellido_m' => self::corto($datos['apellido_m'] ?? null, 60),
            'telefono'   => self::corto($datos['telefono'] ?? null, 20),
            'email'      => ($e = self::texto($datos['email'] ?? null)) ? mb_strtolower($e) : null,
            'genero'     => 'N',
        ]);
        self::ligarTutor($idAlumno, $idTutor, $datos['parentesco'] ?? null);
        return $idTutor;
    }

    /** Liga un tutor que ya existe como el tutor principal del alumno (null lo quita). */
    public static function ligarTutor(int $idAlumno, ?int $idTutor, ?string $parentesco = null): void
    {
        DB::table('core.alumno_tutor')->where('id_alumno', $idAlumno)
            ->when($idTutor, fn ($q) => $q->where('id_tutor', '!=', $idTutor))->delete();
        if (!$idTutor || $idTutor === $idAlumno) {
            return;
        }
        $parentesco = in_array($parentesco, self::PARENTESCOS, true) ? $parentesco
            : (DB::table('core.alumno_tutor')->where('id_tutor', $idTutor)->value('parentesco') ?? 'otro');
        DB::table('core.alumno_tutor')->updateOrInsert(
            ['id_alumno' => $idAlumno, 'id_tutor' => $idTutor],
            ['parentesco' => $parentesco, 'principal' => true, 'autoriza_recoger' => true]
        );
    }

    /**
     * Quita el perfil de ese tipo. Si la persona ya no tiene otro perfil se
     * borran también su cuenta y la persona. Si tiene inscripciones, pagos o
     * grupos la base no lo deja (error 23503).
     */
    public static function eliminarPersona(string $tipo, int $idPersona): void
    {
        DB::table('core.perfil')->where('id_persona', $idPersona)->where('tipo', $tipo)->delete();
        $idUsuario = DB::table('core.usuario')->where('id_persona', $idPersona)->value('id');

        if (DB::table('core.perfil')->where('id_persona', $idPersona)->exists()) {
            if ($idUsuario && $tipo === 'personal') {
                self::rolPersonal((int) $idUsuario, null);
            }
            self::estadoCuenta($idPersona);
            return;
        }

        if ($idUsuario) {
            DB::table('personal_access_tokens')->where('tokenable_type', \App\Models\Core\Usuario::class)
                ->where('tokenable_id', $idUsuario)->delete();
            DB::table('core.usuario_rol')->where('otorgado_por', $idUsuario)->update(['otorgado_por' => null]);
            DB::table('core.usuario')->where('id', $idUsuario)->delete();
        }
        if (!DB::table('core.alumno_tutor')->where('id_tutor', $idPersona)->exists()) {
            DB::table('core.persona')->where('id', $idPersona)->delete();
        }
    }

    /** ¿El correo ya es el login de otra persona? (el login es único en todo el sistema) */
    public static function correoOcupado(string $email, ?int $idPersona = null): bool
    {
        return DB::table('core.usuario')
            ->where('usuario_login', mb_strtolower(trim($email)))
            ->when($idPersona, fn ($q) => $q->where('id_persona', '!=', $idPersona))
            ->exists();
    }

    /** La cuenta está activa si la persona tiene al menos un perfil activo (una suspendida se queda así). */
    private static function estadoCuenta(int $idPersona): void
    {
        $algunoActivo = DB::table('core.perfil')->where('id_persona', $idPersona)->where('estado', 'activo')->exists();
        DB::table('core.usuario')->where('id_persona', $idPersona)->where('estado', '!=', 'suspendido')
            ->update(['estado' => $algunoActivo ? 'activo' : 'inactivo']);
    }

    /** El formulario maneja una sola sede: si no es una de las que ya tiene, reemplaza las anteriores. */
    private static function sedePersonal(int $idPersona, int $idSede): void
    {
        $tiene = DB::table('core.perfil_sede')->where('id_persona', $idPersona)->where('tipo', 'personal')
            ->where('id_sede', $idSede)->exists();
        if (!$tiene) {
            DB::table('core.perfil_sede')->where('id_persona', $idPersona)->where('tipo', 'personal')->delete();
            DB::table('core.perfil_sede')->insert(['id_persona' => $idPersona, 'tipo' => 'personal', 'id_sede' => $idSede]);
        }
        $primera = DB::table('core.perfil_sede')->where('id_persona', $idPersona)->where('tipo', 'personal')
            ->orderBy('asignado_en')->orderBy('id_sede')->value('id_sede');
        DB::table('core.perfil')->where('id_persona', $idPersona)->where('tipo', 'personal')->update(['id_sede' => $primera]);
    }

    /** El personal tiene un solo rol de los de la pantalla de roles (los de sistema no se tocan). */
    private static function rolPersonal(int $idUsuario, ?int $idRol): void
    {
        $deLaPantalla = DB::table('core.rol')->where('es_sistema', false)->pluck('id');
        DB::table('core.usuario_rol')->where('id_usuario', $idUsuario)->whereIn('id_rol', $deLaPantalla)
            ->when($idRol, fn ($q) => $q->where('id_rol', '!=', $idRol))->delete();
        if ($idRol && !DB::table('core.usuario_rol')->where('id_usuario', $idUsuario)->where('id_rol', $idRol)->exists()) {
            DB::table('core.usuario_rol')->insert([
                'id_usuario' => $idUsuario, 'id_rol' => $idRol, 'id_sede' => null,
                'otorgado_por' => session('id_usuario') ?: null,
            ]);
        }
    }

    /** Rol global -> una asignación sin sede; si no, una por cada sede del personal. */
    private static function alcanceRoles(int $idUsuario): void
    {
        $idPersona = (int) DB::table('core.usuario')->where('id', $idUsuario)->value('id_persona');
        $sedes = DB::table('core.perfil_sede')->where('id_persona', $idPersona)->where('tipo', 'personal')->pluck('id_sede')->all();
        if (!$sedes) {
            $sedes = array_filter([DB::table('core.perfil')->where('id_persona', $idPersona)->where('tipo', 'personal')->value('id_sede')]);
        }

        $asignados = DB::table('core.usuario_rol as ur')->join('core.rol as r', 'r.id', '=', 'ur.id_rol')
            ->where('ur.id_usuario', $idUsuario)->where('r.es_sistema', false)
            ->get(['ur.id', 'ur.id_rol', 'ur.id_sede', 'r.global']);

        foreach ($asignados->groupBy('id_rol') as $idRol => $filas) {
            $deseadas = $filas->first()->global ? [null] : $sedes;
            foreach ($filas as $f) {
                if (!in_array($f->id_sede, $deseadas)) {
                    DB::table('core.usuario_rol')->where('id', $f->id)->delete();
                }
            }
            $tiene = $filas->pluck('id_sede')->all();
            foreach ($deseadas as $idSede) {
                if (!in_array($idSede, $tiene, true)) {
                    DB::table('core.usuario_rol')->insert([
                        'id_usuario' => $idUsuario, 'id_rol' => $idRol, 'id_sede' => $idSede,
                        'otorgado_por' => session('id_usuario') ?: null,
                    ]);
                }
            }
        }
    }

    // ===================== SEDES =====================

    /** $datos: nombre, estado_residencia, ciudad, codigo_postal, calle, telefono, email, estatus. */
    public static function sede(?int $idSede, array $datos): int
    {
        $fila = [
            'nombre'        => mb_substr(self::texto($datos['nombre'] ?? null) ?? 'Sede', 0, 80),
            'calle'         => self::corto($datos['calle'] ?? null, 120),
            'ciudad'        => self::corto($datos['ciudad'] ?? null, 80),
            'estado_dir'    => self::corto($datos['estado_residencia'] ?? null, 50),
            'codigo_postal' => self::codigoPostal($datos['codigo_postal'] ?? null),
            'telefono'      => self::corto($datos['telefono'] ?? null, 20),
            'email'         => ($e = self::texto($datos['email'] ?? null)) ? mb_strtolower($e) : null,
        ];
        if (array_key_exists('estatus', $datos)) {
            $fila['activa'] = self::activo($datos['estatus']);
        }

        if ($idSede) {
            DB::table('core.sede')->where('id', $idSede)->update($fila);
            return $idSede;
        }
        $idSede = DB::table('core.sede')->insertGetId($fila + ['clave' => 'S-' . substr(uniqid(), -7)]);
        DB::table('core.sede')->where('id', $idSede)->update(['clave' => self::claveLibre('core.sede', 'S', $idSede)]);
        return $idSede;
    }

    /** Si tiene alumnos, profesores, personal, grupos o pagos la base no la deja borrar (error 23503). */
    public static function eliminarSede(int $idSede): void
    {
        DB::table('core.sede')->where('id', $idSede)->delete();
    }

    // ===================== ROLES =====================

    /** Alta o cambio de un rol. $banderas: las casillas de la pantalla (alumno_ver, alumno_edit, ...). */
    public static function rol(?int $idRol, array $datos, array $banderas): int
    {
        $fila = [
            'nombre'      => mb_substr(self::texto($datos['nombre'] ?? null) ?? 'Rol', 0, 80),
            'descripcion' => self::texto($datos['descripcion'] ?? null),
            'activo'      => self::activo($datos['estatus'] ?? null),
        ];
        if (array_key_exists('administrativo', $datos)) {
            $fila['global'] = (bool) $datos['administrativo'];
        }

        if ($idRol) {
            DB::table('core.rol')->where('id', $idRol)->update($fila);
        } else {
            $idRol = DB::table('core.rol')->insertGetId($fila + ['clave' => 'rol-' . substr(uniqid(), -10), 'es_sistema' => false]);
            DB::table('core.rol')->where('id', $idRol)->update(['clave' => self::claveLibre('core.rol', 'rol_', $idRol)]);
        }

        $global = (bool) DB::table('core.rol')->where('id', $idRol)->value('global');
        $claves = [];
        foreach (self::PERMISOS_DE_BANDERA as $bandera => $destino) {
            if (!empty($banderas[$bandera])) {
                array_push($claves, ...$destino);
                if ($bandera === 'alumno_edit' && $global) {
                    $claves[] = 'alumno.eliminar';   // borrar = editar + rol global, como antes
                }
            }
        }
        DB::table('core.rol_permiso')->where('id_rol', $idRol)->delete();
        $idsPermiso = DB::table('core.permiso')->whereIn('clave', array_unique($claves))->pluck('id');
        DB::table('core.rol_permiso')->insert($idsPermiso->map(fn ($id) => ['id_rol' => $idRol, 'id_permiso' => $id])->all());

        // Si cambió "administrativo", cambia el alcance (global o por sede) de quien tenga el rol
        foreach (DB::table('core.usuario_rol')->where('id_rol', $idRol)->distinct()->pluck('id_usuario') as $idUsuario) {
            self::alcanceRoles((int) $idUsuario);
        }
        return $idRol;
    }

    public static function eliminarRol(int $idRol): void
    {
        DB::table('core.rol')->where('id', $idRol)->where('es_sistema', false)->delete();   // rol_permiso se borra en cascada
    }

    // ===================== CURSOS Y GRUPOS =====================

    /** $datos: nombre, id_sede, nivel (puntaje mínimo de un nivel), duracion_semanas, horas_totales, costo_base, estatus, descripcion, requisitos. */
    public static function curso(?int $idCurso, array $datos): int
    {
        $fila = [
            'nombre'           => mb_substr(self::texto($datos['nombre'] ?? null) ?? 'Curso', 0, 100),
            'id_nivel_minimo'  => isset($datos['nivel']) && $datos['nivel'] !== ''
                ? DB::table('academico.nivel')->where('rating_minimo', (int) $datos['nivel'])->value('id') : null,
            'duracion_semanas' => max(1, min(32767, (int) ($datos['duracion_semanas'] ?? 1))),
            'horas_totales'    => max(1, min(32767, (int) ($datos['horas_totales'] ?? 1))),
            'costo_base'       => max(0, round((float) ($datos['costo_base'] ?? 0), 2)),
            'descripcion'      => self::texto($datos['descripcion'] ?? null),
            'requisitos'       => self::texto($datos['requisitos'] ?? null),
            'activo'           => self::activo($datos['estatus'] ?? null),
        ];

        if ($idCurso) {
            DB::table('academico.curso')->where('id', $idCurso)->update($fila);
        } else {
            $idCurso = DB::table('academico.curso')->insertGetId($fila + ['clave' => 'C-' . substr(uniqid(), -10), 'tipo' => 'regular']);
            DB::table('academico.curso')->where('id', $idCurso)->update(['clave' => self::claveLibre('academico.curso', 'C', $idCurso)]);
        }

        // Un curso se da en una sede; cambiarla mueve también sus grupos
        $idSede = (int) $datos['id_sede'];
        DB::table('academico.curso_sede')->where('id_curso', $idCurso)->where('id_sede', '!=', $idSede)->delete();
        DB::table('academico.curso_sede')->updateOrInsert(['id_curso' => $idCurso, 'id_sede' => $idSede], ['activo' => $fila['activo']]);
        foreach (DB::table('academico.grupo')->where('id_curso', $idCurso)->where('id_sede', '!=', $idSede)->pluck('id') as $idGrupo) {
            DB::table('academico.grupo')->where('id', $idGrupo)->update(['id_sede' => $idSede]);
            DB::table('academico.inscripcion')->where('id_grupo', $idGrupo)->update(['id_sede' => $idSede]);
        }
        return $idCurso;
    }

    /** Si tiene grupos la base no lo deja borrar (error 23503). */
    public static function eliminarCurso(int $idCurso): void
    {
        DB::table('academico.curso')->where('id', $idCurso)->delete();
    }

    /**
     * $datos: id_curso, id_profesor, codigo_grupo, nombre, periodo (clave),
     * fecha_inicio, fecha_fin, cupo_maximo, estatus.
     * $horarios: [{dia_semana, hora_inicio, hora_fin, ubicacion}], se rehacen completos.
     * Lanza ErrorDeDatos si el código se repite o si un horario choca.
     */
    public static function grupo(?int $idGrupo, array $datos, array $horarios): int
    {
        $idSede = (int) DB::table('academico.curso_sede')->where('id_curso', $datos['id_curso'])->orderBy('id_sede')->value('id_sede')
            ?: (int) DB::table('core.perfil')->where('id_persona', $datos['id_profesor'])->where('tipo', 'profesor')->value('id_sede');
        $codigo = mb_substr(trim((string) $datos['codigo_grupo']), 0, 20);

        $repetido = DB::table('academico.grupo')->where('id_sede', $idSede)->where('codigo', $codigo)
            ->when($idGrupo, fn ($q) => $q->where('id', '!=', $idGrupo))->exists();
        if ($repetido) {
            throw new ErrorDeDatos('codigo_grupo', 'Ya hay otro grupo con ese código en la sede.');
        }

        $inicio = substr((string) $datos['fecha_inicio'], 0, 10);
        $fila = [
            'id_sede'      => $idSede,
            'id_curso'     => (int) $datos['id_curso'],
            'id_profesor'  => (int) $datos['id_profesor'],
            'id_periodo'   => self::periodo($inicio, $datos['periodo'] ?? null),
            'codigo'       => $codigo,
            'nombre'       => self::corto($datos['nombre'] ?? null, 100),
            'fecha_inicio' => $inicio,
            'fecha_fin'    => substr((string) $datos['fecha_fin'], 0, 10),
            'cupo_maximo'  => max(1, min(32767, (int) $datos['cupo_maximo'])),
            'estado'       => self::activo($datos['estatus'] ?? null) ? 'abierto' : 'cerrado',
        ];

        if ($idGrupo) {
            DB::table('academico.grupo')->where('id', $idGrupo)->update($fila);
            DB::table('academico.inscripcion')->where('id_grupo', $idGrupo)->where('id_sede', '!=', $idSede)->update(['id_sede' => $idSede]);
        } else {
            $idGrupo = DB::table('academico.grupo')->insertGetId($fila);
        }

        DB::table('academico.horario')->where('id_grupo', $idGrupo)->delete();
        $vistos = [];
        foreach ($horarios as $h) {
            $dia = (int) ($h['dia_semana'] ?? 0) === 0 ? 7 : (int) $h['dia_semana'];
            $ini = substr((string) ($h['hora_inicio'] ?? ''), 0, 5);
            $fin = substr((string) ($h['hora_fin'] ?? ''), 0, 5);
            if ($dia < 1 || $dia > 7 || $ini === '' || $fin <= $ini) {
                throw new ErrorDeDatos('horarios', 'Revisa los horarios: cada uno necesita día y una hora de fin después de la de inicio.');
            }
            if (isset($vistos["$dia|$ini|$fin"])) {
                continue;
            }
            $vistos["$dia|$ini|$fin"] = true;
            self::insertarHorario($idGrupo, $dia, $ini, $fin, self::aula($idSede, $h['ubicacion'] ?? null));
        }
        return $idGrupo;
    }

    /** Si tiene historial de inscripciones o pagos la base no lo deja borrar (error 23503). */
    public static function eliminarGrupo(int $idGrupo): void
    {
        DB::table('academico.grupo')->where('id', $idGrupo)->delete();   // los horarios se borran en cascada
    }

    // ===================== EXTRAESCOLARES =====================

    /**
     * Una extraescolar es un curso tipo 'extraescolar' con un grupo (sin
     * profesor, en la sede principal). Regresa el id del curso.
     * $datos: nombre, cupo_maximo, duracion_semanas, costo_base, fecha_inicio,
     * fecha_fin, estatus, ubicacion, descripcion, requisitos.
     */
    public static function extraescolar(?int $idCurso, array $datos): int
    {
        $activo = self::activo($datos['estatus'] ?? null);
        $curso = [
            'nombre'           => mb_substr(self::texto($datos['nombre'] ?? null) ?? 'Extraescolar', 0, 100),
            'tipo'             => 'extraescolar',
            'duracion_semanas' => max(1, min(32767, (int) ($datos['duracion_semanas'] ?? 1))),
            'horas_totales'    => max(1, min(32767, (int) ($datos['duracion_semanas'] ?? 1))),
            'costo_base'       => max(0, round((float) ($datos['costo_base'] ?? 0), 2)),
            'descripcion'      => self::texto($datos['descripcion'] ?? null),
            'requisitos'       => self::texto($datos['requisitos'] ?? null),
            'activo'           => $activo,
        ];

        if ($idCurso) {
            DB::table('academico.curso')->where('id', $idCurso)->update($curso);
        } else {
            $idCurso = DB::table('academico.curso')->insertGetId($curso + ['clave' => 'E-' . substr(uniqid(), -10)]);
            DB::table('academico.curso')->where('id', $idCurso)->update(['clave' => self::claveLibre('academico.curso', 'E', $idCurso)]);
        }

        $grupo = DB::table('academico.grupo')->where('id_curso', $idCurso)->orderBy('id')->first();
        $idSede = $grupo->id_sede ?? self::sedePrincipal();
        DB::table('academico.curso_sede')->updateOrInsert(['id_curso' => $idCurso, 'id_sede' => $idSede], ['activo' => $activo]);

        $inicio = substr((string) $datos['fecha_inicio'], 0, 10);
        $fin = substr((string) $datos['fecha_fin'], 0, 10);
        if ($fin <= $inicio) {
            $fin = \Carbon\Carbon::parse($inicio)->addDay()->toDateString();   // v2.2 pide fin posterior al inicio
        }
        $fila = [
            'id_sede'      => $idSede,
            'id_curso'     => $idCurso,
            'id_periodo'   => self::periodo($inicio),
            'fecha_inicio' => $inicio,
            'fecha_fin'    => $fin,
            'ubicacion'    => self::corto($datos['ubicacion'] ?? null, 100),
            'cupo_maximo'  => max(1, min(32767, (int) ($datos['cupo_maximo'] ?? 0) ?: 1)),
            'estado'       => $activo ? 'abierto' : 'cerrado',
        ];
        if ($grupo) {
            DB::table('academico.grupo')->where('id', $grupo->id)->update($fila);
        } else {
            DB::table('academico.grupo')->insert($fila + ['codigo' => self::claveLibre('academico.grupo', 'EXT-', $idCurso, $idSede)]);
        }
        return $idCurso;
    }

    /** Si tiene historial de inscripciones o pagos la base no la deja borrar (error 23503). */
    public static function eliminarExtraescolar(int $idCurso): void
    {
        DB::table('academico.grupo')->where('id_curso', $idCurso)->delete();
        DB::table('academico.curso')->where('id', $idCurso)->delete();
    }

    /** Grupo de una extraescolar (id del curso). */
    public static function grupoDeExtraescolar(int $idCurso): ?int
    {
        $id = DB::table('academico.grupo')->where('id_curso', $idCurso)->orderBy('id')->value('id');
        return $id !== null ? (int) $id : null;
    }

    // ===================== INSCRIPCIONES =====================

    /** Inscribe al alumno; lanza ErrorDeDatos si el grupo ya no tiene cupo. */
    public static function inscribir(int $idAlumno, int $idGrupo): int
    {
        try {
            return DB::transaction(fn () => DB::table('academico.inscripcion')->insertGetId([
                'id_alumno'    => $idAlumno,
                'id_grupo'     => $idGrupo,
                'id_sede'      => DB::table('academico.grupo')->where('id', $idGrupo)->value('id_sede'),
                'estado'       => 'activa',
                'inscrito_por' => session('id_usuario') ?: null,
            ]));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23514') {
                throw new ErrorDeDatos('cupo', 'El grupo está lleno.');
            }
            throw $e;
        }
    }

    public static function darDeBaja(int $idInscripcion, string $motivo): void
    {
        DB::table('academico.inscripcion')->where('id', $idInscripcion)->whereIn('estado', ['activa', 'lista_espera'])
            ->update(['estado' => 'baja', 'fecha_baja' => now(), 'motivo_baja' => $motivo]);
    }

    // ===================== PAGOS =====================

    /**
     * Cargo al alumno. $concepto: clave de finanzas.concepto ('inscripcion' o
     * 'extraescolar'). La descripción guarda "concepto | descripción" como antes.
     */
    public static function cargo(int $idAlumno, ?int $idInscripcion, string $concepto, string $texto, ?string $descripcion, float $total, string $fechaLimite): int
    {
        $idSede = $idInscripcion
            ? DB::table('academico.inscripcion')->where('id', $idInscripcion)->value('id_sede')
            : DB::table('core.perfil')->where('id_persona', $idAlumno)->where('tipo', 'alumno')->value('id_sede');

        return DB::table('finanzas.cargo')->insertGetId([
            'id_sede'        => $idSede,
            'id_alumno'      => $idAlumno,
            'id_concepto'    => DB::table('finanzas.concepto')->where('clave', $concepto)->value('id'),
            'id_inscripcion' => $idInscripcion,
            'descripcion'    => trim($texto . ($descripcion ? ' | ' . $descripcion : '')) ?: null,
            'subtotal'       => max(0, round($total, 2)),
            'fecha_limite'   => substr($fechaLimite, 0, 10),
            'estado'         => 'pendiente',
            'emitido_por'    => session('id_usuario') ?: null,
        ]);
    }

    /** Marca el cargo como pagado: registra un pago por lo que falta y el trigger pone el estado. */
    public static function pagarCargo(int $idCargo): void
    {
        $cargo = DB::table('finanzas.cargo')->where('id', $idCargo)->first();
        if (!$cargo || $cargo->estado === 'pagado') {
            return;
        }
        if ($cargo->estado === 'cancelado') {
            DB::table('finanzas.cargo')->where('id', $idCargo)->update(['estado' => 'pendiente', 'cancelado_en' => null, 'motivo_cancelacion' => null]);
        }
        $pagado = (float) DB::table('finanzas.pago')->where('id_cargo', $idCargo)->sum('monto');
        $falta = round((float) $cargo->total - $pagado, 2);
        if ($falta > 0) {
            DB::table('finanzas.pago')->insert([
                'id_cargo' => $idCargo, 'monto' => $falta, 'metodo' => 'otro',
                'registrado_por' => session('id_usuario') ?: null,
            ]);
        } else {
            DB::table('finanzas.cargo')->where('id', $idCargo)->update(['estado' => 'pagado']);
        }
    }

    public static function cancelarCargo(int $idCargo, string $motivo = 'Cancelado'): void
    {
        DB::table('finanzas.cargo')->where('id', $idCargo)->where('estado', '!=', 'cancelado')
            ->update(['estado' => 'cancelado', 'cancelado_en' => now(), 'motivo_cancelacion' => $motivo]);
    }

    /** Pasa a vencido lo pendiente de un alumno cuya fecha límite ya pasó. */
    public static function vencerCargos(int $idAlumno): void
    {
        DB::table('finanzas.cargo')->where('id_alumno', $idAlumno)->whereIn('estado', ['pendiente', 'parcial'])
            ->where('fecha_limite', '<', now()->toDateString())->update(['estado' => 'vencido']);
    }

    // ===================== AYUDAS =====================

    /** Sede donde quedan las extraescolares y los perfiles sin sede: la activa más antigua. */
    private static function sedePrincipal(): int
    {
        return (int) DB::table('core.sede')->orderByDesc('activa')->orderBy('id')->value('id');
    }

    /** Clave única "<prefijo><id>" (con sufijo si ya está tomada). */
    private static function claveLibre(string $tabla, string $prefijo, int $id, ?int $idSede = null): string
    {
        $columna = $tabla === 'academico.grupo' ? 'codigo' : 'clave';
        $clave = $prefijo . $id;
        for ($n = 2; DB::table($tabla)->where($columna, $clave)->when($idSede, fn ($q) => $q->where('id_sede', $idSede))->exists(); $n++) {
            $clave = $prefijo . $id . '-' . $n;
        }
        return $clave;
    }

    /** El periodo elegido (su clave); si no hay, el año de la fecha de inicio (se crea si no existe). */
    private static function periodo(string $fechaInicio, ?string $clave = null): int
    {
        $id = $clave !== null && trim($clave) !== ''
            ? DB::table('academico.periodo')->where('clave', trim($clave))->value('id') : null;
        $id ??= DB::table('academico.periodo')->whereRaw('vigencia @> ?::date', [$fechaInicio])->value('id');
        if ($id) {
            return (int) $id;
        }
        $anio = (int) substr($fechaInicio, 0, 4);
        return (int) DB::table('academico.periodo')->insertGetId([
            'clave' => (string) $anio, 'nombre' => 'Año ' . $anio,
            'vigencia' => DB::raw("daterange('{$anio}-01-01', '" . ($anio + 1) . "-01-01')"),
        ]);
    }

    /** Aula de la sede con ese nombre (la "ubicación" del horario); se crea si no existe. */
    private static function aula(int $idSede, $ubicacion): ?int
    {
        $nombre = self::corto($ubicacion, 40);
        if (!$nombre) {
            return null;
        }
        $id = DB::table('core.aula')->where('id_sede', $idSede)->where('nombre', $nombre)->value('id');
        return (int) ($id ?? DB::table('core.aula')->insertGetId(['id_sede' => $idSede, 'nombre' => $nombre]));
    }

    /** v2.2 no deja empalmar un aula ni a un profesor: se avisa en vez de guardar. */
    private static function insertarHorario(int $idGrupo, int $dia, string $ini, string $fin, ?int $idAula): void
    {
        try {
            DB::transaction(fn () => DB::table('academico.horario')->insert([
                'id_grupo' => $idGrupo, 'id_aula' => $idAula, 'dia_semana' => $dia,
                'horas' => DB::raw("academico.rango_hora('{$ini}'::time, '{$fin}'::time)"),
                'vigencia' => DB::raw("'empty'::daterange"),   // la pone el trigger con las fechas del grupo
            ]));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() !== '23P01') {
                throw $e;
            }
            $dias = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
            $quien = str_contains($e->getMessage(), 'profesor') ? 'El profesor ya tiene otra clase' : 'El aula ya está ocupada';
            throw new ErrorDeDatos('horarios', "{$quien} el {$dias[$dia]} de {$ini} a {$fin}.");
        }
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
