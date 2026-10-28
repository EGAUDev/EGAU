<?php
namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lectura desde v2.2 con la forma de las tablas viejas.
 *
 * Cada método regresa una consulta sobre core.* / academico.* / finanzas.*
 * con los mismos nombres de columna e ids de v1 (traducidos con
 * migracion.mapa), para usarse como tabla: DB::query()->fromSub(LecturaCore::sedes(), 'sede').
 * Así las pantallas, el JavaScript y los métodos que guardan no cambian.
 *
 * Solo el id_permiso del rol se sigue tomando de v1 (lo usa la pantalla de roles al guardar).
 */
class LecturaCore
{
    /** Join a migracion.mapa: $col (id de v2.2) -> id de v1 de esa tabla. */
    private static function idLegacy(string $tabla, string $col): string
    {
        return "(SELECT mm.id_origen FROM migracion.mapa mm WHERE mm.tabla_origen = '{$tabla}' AND mm.id_nuevo = {$col} ORDER BY mm.id_origen LIMIT 1)";
    }

    public static function sedes(): Builder
    {
        return DB::table('core.sede as s')
            ->join('migracion.mapa as m', fn ($j) => $j->on('m.id_nuevo', '=', 's.id')->where('m.tabla_origen', 'sede'))
            ->selectRaw('m.id_origen AS id_sede, s.nombre, s.estado_dir AS estado_residencia, s.ciudad,
                         s.codigo_postal::int AS codigo_postal, s.calle, s.telefono, s.email, s.activa AS estatus');
    }

    // Bandera de public.permiso -> clave de core.permiso (la misma equivalencia que usa SincronizadorCore)
    private const BANDERAS = [
        'alumno_ver' => 'alumno.ver', 'alumno_edit' => 'alumno.editar',
        'profesor_ver' => 'profesor.ver', 'profesor_edit' => 'profesor.editar',
        'personal_ver' => 'personal.ver', 'personal_edit' => 'personal.editar',
        'roles_ver' => 'rol.ver', 'roles_edit' => 'rol.editar',
        'sedes_ver' => 'sede.ver', 'sedes_edit' => 'sede.editar',
        'grupos_ver' => 'grupo.ver', 'grupos_edit' => 'grupo.editar',
        'extracurriculares_ver' => 'extraescolar.ver', 'extracurriculares_edit' => 'extraescolar.editar',
        'estatus_ver' => 'estatus.ver', 'pagos_ver' => 'cargo.ver', 'pagos_edit' => 'cargo.editar',
        'niveles_ver' => 'nivel.ver', 'niveles_edit' => 'nivel.editar',
    ];

    /**
     * Roles con sus permisos como banderas (antes rol JOIN permiso). Las banderas
     * salen de core.rol_permiso; "administrativo" es core.rol.global. id_permiso sigue en v1.
     */
    public static function roles(): Builder
    {
        $q = DB::table('core.rol as r')
            ->join('migracion.mapa as m', fn ($j) => $j->on('m.id_nuevo', '=', 'r.id')->where('m.tabla_origen', 'rol'))
            ->join('public.rol as v1', 'v1.id_rol', '=', 'm.id_origen')
            ->selectRaw('m.id_origen AS id_rol, v1.id_permiso, r.nombre, r.descripcion, r.activo AS estatus,
                         r.creado_en::timestamp(0) AS fecha_registro, r.actualizado_en::timestamp(0) AS fecha_modificacion,
                         r.global AS administrativo');
        foreach (self::BANDERAS as $bandera => $clave) {
            $q->selectRaw("EXISTS (SELECT 1 FROM core.rol_permiso rp JOIN core.permiso p ON p.id = rp.id_permiso
                                    WHERE rp.id_rol = r.id AND p.clave = ?) AS {$bandera}", [$clave]);
        }
        return $q;
    }

    /** alumno, profesor o personal: persona + su perfil de ese tipo. */
    public static function personas(string $tipo): Builder
    {
        $q = DB::table('migracion.mapa as m')
            ->join('core.persona as pe', 'pe.id', '=', 'm.id_nuevo')
            ->join('core.perfil as pf', fn ($j) => $j->on('pf.id_persona', '=', 'pe.id')->where('pf.tipo', $tipo))
            ->where('m.tabla_origen', $tipo)
            ->selectRaw("m.id_origen AS id_{$tipo}, pe.nombre, pe.apellido_p, pe.apellido_m,
                         pe.estado_dir AS estado_residencia, pe.ciudad, pe.calle, pe.codigo_postal::int AS codigo_postal,
                         NULLIF(lower(pe.genero), 'n') AS genero, pe.fecha_nacimiento,
                         pf.fecha_inicio::timestamp AS fecha_registro, pe.actualizado_en::timestamp(0) AS fecha_modificacion,
                         pe.telefono, pe.email, pf.estado = 'activo' AS estatus");

        if ($tipo === 'personal') {
            // Rol de v1 = el rol legacy que tiene asignado en core.usuario_rol
            return $q->selectRaw('(SELECT mr.id_origen FROM core.usuario u
                                     JOIN core.usuario_rol ur ON ur.id_usuario = u.id
                                     JOIN migracion.mapa mr ON mr.tabla_origen = \'rol\' AND mr.id_nuevo = ur.id_rol
                                    WHERE u.id_persona = pe.id ORDER BY ur.id LIMIT 1) AS id_rol');
        }

        $q->selectRaw(self::idLegacy('sede', 'pf.id_sede') . ' AS id_sede, pe.rating AS puntaje');
        if ($tipo === 'alumno') {
            $q->selectRaw("(SELECT mt.id_origen FROM core.alumno_tutor at
                              JOIN migracion.mapa mt ON mt.tabla_origen = 'tutor' AND mt.id_nuevo = at.id_tutor
                             WHERE at.id_alumno = pe.id AND at.principal LIMIT 1) AS id_tutor");
        }
        return $q;
    }

    /** Sedes del personal (antes usuariosede): una fila por persona y sede. */
    public static function sedesPersonal(): Builder
    {
        return DB::table('migracion.mapa as m')
            ->join('core.perfil_sede as ps', fn ($j) => $j->on('ps.id_persona', '=', 'm.id_nuevo')->where('ps.tipo', 'personal'))
            ->where('m.tabla_origen', 'personal')
            ->selectRaw('m.id_origen AS id_personal, ' . self::idLegacy('sede', 'ps.id_sede') . ' AS id_sede, ps.asignado_en AS fecha_asignacion');
    }

    /** Cursos regulares (las extraescolares son cursos aparte). "nivel" es el puntaje mínimo del nivel que pide. */
    public static function cursos(): Builder
    {
        return DB::table('academico.curso as c')
            ->join('migracion.mapa as m', fn ($j) => $j->on('m.id_nuevo', '=', 'c.id')->where('m.tabla_origen', 'curso'))
            ->leftJoin('academico.nivel as n', 'n.id', '=', 'c.id_nivel_minimo')
            ->selectRaw("m.id_origen AS id_curso,
                         (SELECT ms.id_origen FROM academico.curso_sede cs
                            JOIN migracion.mapa ms ON ms.tabla_origen = 'sede' AND ms.id_nuevo = cs.id_sede
                           WHERE cs.id_curso = c.id ORDER BY cs.id_sede LIMIT 1) AS id_sede,
                         c.nombre, n.rating_minimo AS nivel, c.duracion_semanas, c.horas_totales, c.costo_base, c.activo AS estatus,
                         c.descripcion, c.requisitos");
    }

    /** Grupos con su cupo ocupado calculado; el periodo es la clave de academico.periodo. */
    public static function grupos(): Builder
    {
        return DB::table('academico.grupo as g')
            ->join('migracion.mapa as m', fn ($j) => $j->on('m.id_nuevo', '=', 'g.id')->where('m.tabla_origen', 'grupo'))
            ->join('academico.periodo as p', 'p.id', '=', 'g.id_periodo')
            ->join('academico.v_grupo_cupo as vc', 'vc.id_grupo', '=', 'g.id')
            ->selectRaw('m.id_origen AS id_grupo, ' . self::idLegacy('curso', 'g.id_curso') . ' AS id_curso, '
                . self::idLegacy('profesor', 'g.id_profesor') . " AS id_profesor,
                         g.codigo AS codigo_grupo, p.clave AS periodo, g.fecha_inicio, g.fecha_fin, g.cupo_maximo,
                         g.estado IN ('planeado', 'abierto', 'en_curso') AS estatus, vc.inscritos AS cupo_actual, g.nombre");
    }

    /** Horarios de los grupos; la ubicación es el aula. */
    public static function horarios(): Builder
    {
        return DB::table('academico.horario as h')
            ->join('migracion.mapa as m', fn ($j) => $j->on('m.id_nuevo', '=', 'h.id_grupo')->where('m.tabla_origen', 'grupo'))
            ->leftJoin('core.aula as a', 'a.id', '=', 'h.id_aula')
            ->selectRaw('h.id AS id_horario, m.id_origen AS id_grupo, h.dia_semana,
                         lower(h.horas) AS hora_inicio, upper(h.horas) AS hora_fin, a.nombre AS ubicacion');
    }

    /** Inscripciones a grupos. Lista de espera cuenta como inscrita, igual que en v1. */
    public static function inscripciones(): Builder
    {
        return DB::table('academico.inscripcion as i')
            ->join('migracion.mapa as m', fn ($j) => $j->on('m.id_nuevo', '=', 'i.id')->where('m.tabla_origen', 'inscripcion'))
            ->selectRaw('m.id_origen AS id_inscripcion, ' . self::idLegacy('alumno', 'i.id_alumno') . ' AS id_alumno, '
                . self::idLegacy('grupo', 'i.id_grupo') . " AS id_grupo,
                         i.fecha_alta::timestamp AS fecha_asignacion, i.fecha_baja::timestamp AS fecha_baja, i.motivo_baja,
                         i.estado IN ('activa', 'lista_espera') AS estatus");
    }

    /** Extraescolares: curso tipo extraescolar + su único grupo (ahí va la ubicación). */
    public static function extraescolares(): Builder
    {
        return DB::table('academico.curso as c')
            ->join('migracion.mapa as m', fn ($j) => $j->on('m.id_nuevo', '=', 'c.id')->where('m.tabla_origen', 'extraescolar'))
            ->join('migracion.mapa as mg', fn ($j) => $j->on('mg.id_origen', '=', 'm.id_origen')->where('mg.tabla_origen', 'extraescolar_grupo'))
            ->join('academico.grupo as g', 'g.id', '=', 'mg.id_nuevo')
            ->join('academico.v_grupo_cupo as vc', 'vc.id_grupo', '=', 'g.id')
            ->selectRaw("m.id_origen AS id_extraescolar, c.nombre, g.cupo_maximo, c.duracion_semanas, c.costo_base,
                         g.fecha_inicio, g.fecha_fin, c.activo AS estatus, g.ubicacion, c.descripcion, c.requisitos,
                         vc.inscritos AS cupo_actual");
    }

    /** Inscripciones a extraescolares (antes inscripcionextraescolar). */
    public static function inscripcionesExtra(): Builder
    {
        return DB::table('academico.inscripcion as i')
            ->join('migracion.mapa as m', fn ($j) => $j->on('m.id_nuevo', '=', 'i.id')->where('m.tabla_origen', 'inscripcionextra'))
            ->selectRaw('m.id_origen AS id_inscripcionextra, ' . self::idLegacy('alumno', 'i.id_alumno') . ' AS id_alumno, '
                . self::idLegacy('extraescolar_grupo', 'i.id_grupo') . " AS id_extraescolar,
                         i.fecha_alta::timestamp AS fecha_asignacion, i.estado IN ('activa', 'lista_espera') AS status");
    }

    /**
     * Cargos con la forma de public.factura. Concepto y descripción se guardaron
     * juntos ("concepto | descripción"); la fecha límite vence al final del día.
     */
    public static function facturas(): Builder
    {
        return DB::table('finanzas.cargo as c')
            ->join('migracion.mapa as m', fn ($j) => $j->on('m.id_nuevo', '=', 'c.id')->where('m.tabla_origen', 'factura'))
            ->selectRaw('m.id_origen AS id_factura, ' . self::idLegacy('alumno', 'c.id_alumno') . ' AS id_alumno, '
                . self::idLegacy('inscripcion', 'c.id_inscripcion') . ' AS id_inscripcion, '
                . self::idLegacy('inscripcionextra', 'c.id_inscripcion') . " AS id_inscripcionextra,
                         c.fecha_emision::timestamp AS fecha_emision, c.fecha_limite + time '23:59:59' AS fecha_limite,
                         c.total AS total_pago,
                         CASE c.estado WHEN 'pagado' THEN 'pagado' WHEN 'cancelado' THEN 'cancelado'
                                       WHEN 'vencido' THEN 'expirado' ELSE 'enproceso' END AS vigencia,
                         CASE WHEN strpos(c.descripcion, ' | ') > 0 THEN substr(c.descripcion, strpos(c.descripcion, ' | ') + 3) END AS descripcion,
                         split_part(c.descripcion, ' | ', 1) AS concepto");
    }

    /** Tutores (antes public.tutor): personas ligadas como tutor de algún alumno. */
    public static function tutores(): Builder
    {
        return DB::table('migracion.mapa as m')
            ->join('core.persona as pe', 'pe.id', '=', 'm.id_nuevo')
            ->where('m.tabla_origen', 'tutor')
            ->selectRaw("m.id_origen AS id_tutor, pe.nombre, pe.apellido_p, pe.apellido_m,
                         (SELECT at.parentesco FROM core.alumno_tutor at WHERE at.id_tutor = pe.id LIMIT 1) AS parentesco,
                         pe.telefono, pe.email");
    }
}
