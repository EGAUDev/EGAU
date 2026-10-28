<?php
namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lectura desde v2.2 con la forma de las tablas viejas.
 *
 * Cada método regresa una consulta sobre core.* / academico.* / finanzas.*
 * con los nombres de columna que usaban las pantallas, para usarse como
 * tabla: DB::query()->fromSub(LecturaCore::sedes(), 'sede').
 * Los ids son los de v2.2: alumno, profesor, personal y tutor son el id de
 * core.persona; una extraescolar es el id de su curso.
 */
class LecturaCore
{
    public static function sedes(): Builder
    {
        return DB::table('core.sede as s')
            ->selectRaw('s.id AS id_sede, s.nombre, s.estado_dir AS estado_residencia, s.ciudad,
                         s.codigo_postal::int AS codigo_postal, s.calle, s.telefono, s.email, s.activa AS estatus');
    }

    // Bandera de la pantalla de roles -> clave de core.permiso (la misma equivalencia que usa EscrituraCore)
    public const BANDERAS = [
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
     * Roles que se asignan desde la pantalla (los de sistema no), con sus
     * permisos como banderas. "administrativo" es core.rol.global.
     */
    public static function roles(): Builder
    {
        $q = DB::table('core.rol as r')
            ->where('r.es_sistema', false)
            ->selectRaw('r.id AS id_rol, r.nombre, r.descripcion, r.activo AS estatus,
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
        $q = DB::table('core.perfil as pf')
            ->join('core.persona as pe', 'pe.id', '=', 'pf.id_persona')
            ->where('pf.tipo', $tipo)
            ->selectRaw("pe.id AS id_{$tipo}, pe.nombre, pe.apellido_p, pe.apellido_m,
                         pe.estado_dir AS estado_residencia, pe.ciudad, pe.calle, pe.codigo_postal::int AS codigo_postal,
                         NULLIF(lower(pe.genero), 'n') AS genero, pe.fecha_nacimiento,
                         pf.fecha_inicio::timestamp AS fecha_registro, pe.actualizado_en::timestamp(0) AS fecha_modificacion,
                         pe.telefono, pe.email, pf.estado = 'activo' AS estatus");

        if ($tipo === 'personal') {
            // El rol del personal es el que tiene asignado de los que se manejan en la pantalla de roles
            return $q->selectRaw('(SELECT ur.id_rol FROM core.usuario u
                                     JOIN core.usuario_rol ur ON ur.id_usuario = u.id
                                     JOIN core.rol r ON r.id = ur.id_rol AND NOT r.es_sistema
                                    WHERE u.id_persona = pe.id ORDER BY ur.id LIMIT 1) AS id_rol');
        }

        $q->selectRaw('pf.id_sede, pe.rating AS puntaje');
        if ($tipo === 'alumno') {
            $q->selectRaw('(SELECT at.id_tutor FROM core.alumno_tutor at
                             WHERE at.id_alumno = pe.id ORDER BY at.principal DESC LIMIT 1) AS id_tutor');
        }
        return $q;
    }

    /** Sedes del personal (antes usuariosede): una fila por persona y sede. */
    public static function sedesPersonal(): Builder
    {
        return DB::table('core.perfil_sede as ps')
            ->where('ps.tipo', 'personal')
            ->selectRaw('ps.id_persona AS id_personal, ps.id_sede, ps.asignado_en AS fecha_asignacion');
    }

    /** Cursos regulares (las extraescolares son cursos aparte). "nivel" es el puntaje mínimo del nivel que pide. */
    public static function cursos(): Builder
    {
        return DB::table('academico.curso as c')
            ->leftJoin('academico.nivel as n', 'n.id', '=', 'c.id_nivel_minimo')
            ->where('c.tipo', 'regular')
            ->selectRaw("c.id AS id_curso,
                         (SELECT cs.id_sede FROM academico.curso_sede cs WHERE cs.id_curso = c.id ORDER BY cs.id_sede LIMIT 1) AS id_sede,
                         c.nombre, n.rating_minimo AS nivel, c.duracion_semanas, c.horas_totales, c.costo_base, c.activo AS estatus,
                         c.descripcion, c.requisitos");
    }

    /** Grupos de cursos regulares con su cupo ocupado calculado; el periodo es la clave de academico.periodo. */
    public static function grupos(): Builder
    {
        return DB::table('academico.grupo as g')
            ->join('academico.curso as c', fn ($j) => $j->on('c.id', '=', 'g.id_curso')->where('c.tipo', 'regular'))
            ->join('academico.periodo as p', 'p.id', '=', 'g.id_periodo')
            ->join('academico.v_grupo_cupo as vc', 'vc.id_grupo', '=', 'g.id')
            ->selectRaw("g.id AS id_grupo, g.id_curso, g.id_profesor, g.id_sede,
                         g.codigo AS codigo_grupo, p.clave AS periodo, g.fecha_inicio, g.fecha_fin, g.cupo_maximo,
                         g.estado IN ('planeado', 'abierto', 'en_curso') AS estatus, vc.inscritos AS cupo_actual, g.nombre");
    }

    /** Horarios de los grupos; la ubicación es el aula. */
    public static function horarios(): Builder
    {
        return DB::table('academico.horario as h')
            ->leftJoin('core.aula as a', 'a.id', '=', 'h.id_aula')
            ->selectRaw('h.id AS id_horario, h.id_grupo, h.dia_semana,
                         lower(h.horas) AS hora_inicio, upper(h.horas) AS hora_fin, a.nombre AS ubicacion');
    }

    /** Inscripciones a grupos de cursos regulares. Lista de espera cuenta como inscrita, igual que antes. */
    public static function inscripciones(): Builder
    {
        return DB::table('academico.inscripcion as i')
            ->join('academico.grupo as g', 'g.id', '=', 'i.id_grupo')
            ->join('academico.curso as c', fn ($j) => $j->on('c.id', '=', 'g.id_curso')->where('c.tipo', 'regular'))
            ->selectRaw("i.id AS id_inscripcion, i.id_alumno, i.id_grupo,
                         i.fecha_alta::timestamp AS fecha_asignacion, i.fecha_baja::timestamp AS fecha_baja, i.motivo_baja,
                         i.estado IN ('activa', 'lista_espera') AS estatus");
    }

    /** Extraescolares: curso tipo extraescolar + su único grupo (ahí van fechas, cupo y ubicación). */
    public static function extraescolares(): Builder
    {
        return DB::table('academico.curso as c')
            ->join('academico.grupo as g', 'g.id', '=', DB::raw('(SELECT min(g2.id) FROM academico.grupo g2 WHERE g2.id_curso = c.id)'))
            ->join('academico.v_grupo_cupo as vc', 'vc.id_grupo', '=', 'g.id')
            ->where('c.tipo', 'extraescolar')
            ->selectRaw("c.id AS id_extraescolar, g.id AS id_grupo, c.nombre, g.cupo_maximo, c.duracion_semanas, c.costo_base,
                         g.fecha_inicio, g.fecha_fin, c.activo AS estatus, g.ubicacion, c.descripcion, c.requisitos,
                         vc.inscritos AS cupo_actual");
    }

    /** Inscripciones a extraescolares (antes inscripcionextraescolar). */
    public static function inscripcionesExtra(): Builder
    {
        return DB::table('academico.inscripcion as i')
            ->join('academico.grupo as g', 'g.id', '=', 'i.id_grupo')
            ->join('academico.curso as c', fn ($j) => $j->on('c.id', '=', 'g.id_curso')->where('c.tipo', 'extraescolar'))
            ->selectRaw("i.id AS id_inscripcionextra, i.id_alumno, g.id_curso AS id_extraescolar,
                         i.fecha_alta::timestamp AS fecha_asignacion, i.estado IN ('activa', 'lista_espera') AS status");
    }

    /**
     * Cargos con la forma de public.factura. Concepto y descripción se guardan
     * juntos ("concepto | descripción"); la fecha límite vence al final del día.
     * id_inscripcion o id_inscripcionextra según el curso de la inscripción.
     */
    public static function facturas(): Builder
    {
        return DB::table('finanzas.cargo as c')
            ->leftJoin('academico.inscripcion as i', 'i.id', '=', 'c.id_inscripcion')
            ->leftJoin('academico.grupo as g', 'g.id', '=', 'i.id_grupo')
            ->leftJoin('academico.curso as cu', 'cu.id', '=', 'g.id_curso')
            ->selectRaw("c.id AS id_factura, c.id_alumno,
                         CASE WHEN cu.tipo = 'extraescolar' THEN NULL ELSE c.id_inscripcion END AS id_inscripcion,
                         CASE WHEN cu.tipo = 'extraescolar' THEN c.id_inscripcion END AS id_inscripcionextra,
                         c.fecha_emision::timestamp AS fecha_emision, c.fecha_limite + time '23:59:59' AS fecha_limite,
                         c.total AS total_pago,
                         CASE c.estado WHEN 'pagado' THEN 'pagado' WHEN 'cancelado' THEN 'cancelado'
                                       WHEN 'vencido' THEN 'expirado' ELSE 'enproceso' END AS vigencia,
                         CASE WHEN strpos(c.descripcion, ' | ') > 0 THEN substr(c.descripcion, strpos(c.descripcion, ' | ') + 3) END AS descripcion,
                         split_part(c.descripcion, ' | ', 1) AS concepto");
    }

    /** Tutores: personas ligadas como tutor de algún alumno. */
    public static function tutores(): Builder
    {
        return DB::table('core.persona as pe')
            ->whereExists(fn ($q) => $q->from('core.alumno_tutor as at')->whereColumn('at.id_tutor', 'pe.id'))
            ->selectRaw("pe.id AS id_tutor, pe.nombre, pe.apellido_p, pe.apellido_m,
                         (SELECT at.parentesco FROM core.alumno_tutor at WHERE at.id_tutor = pe.id LIMIT 1) AS parentesco,
                         pe.telefono, pe.email");
    }
}
