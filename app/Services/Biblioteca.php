<?php
namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lectura de la biblioteca (biblioteca.recurso) para el personal y para el alumno.
 *  - consulta(): recursos con filtros (tipo, nivel, año, ECO, estatus, sede, texto).
 *  - paraAlumno(): solo material publicado de su sede (o general) y de su nivel o menor (SCRUM-47).
 *  - fila(): el formato que usan las vistas (el mismo de los datos de prueba).
 */
class Biblioteca
{
    /** tipo en la base => nombre en pantalla */
    public const TIPOS = ['libro' => 'Libro', 'partida' => 'Partida', 'video' => 'Video', 'problema' => 'Ejercicio', 'apertura' => 'Apertura'];
    public const IDIOMAS = ['es' => 'Español', 'en' => 'Inglés', 'fr' => 'Francés', 'pt' => 'Portugués', 'ru' => 'Ruso', 'de' => 'Alemán'];

    /** Acepta 'Libro', 'libro', 'Ejercicio' o 'problema'. */
    public static function tipoClave(?string $tipo): ?string
    {
        if ($tipo === null || $tipo === '') return null;
        $t = Buscador::sinAcentos(trim($tipo));
        foreach (self::TIPOS as $clave => $nombre) {
            if ($t === $clave || $t === Buscador::sinAcentos($nombre)) return $clave;
        }
        return null;
    }

    public static function consulta(array $f = []): Builder
    {
        $q = DB::table('biblioteca.recurso as r')
            ->leftJoin('academico.nivel as n', 'n.id', '=', 'r.id_nivel_minimo')
            ->leftJoin('core.sede as s', 's.id', '=', 'r.id_sede')
            ->selectRaw("r.id, r.uid, r.tipo, r.titulo, r.titulo_es, r.slug, r.eco, r.idioma, r.edad_minima, r.fuente, r.licencia,
                         r.publicado, r.id_sede, s.nombre AS nombre_sede, r.id_nivel_minimo, n.rating_minimo AS nivel_min,
                         r.metadatos, r.creado_en, r.actualizado_en,
                         (r.contenido - 'pgn' - 'fens' - 'jugadas' - 'jugadas_es' - 'variantes') AS resumen");

        // tipo: uno o varios separados por coma ("partida,apertura"); sin_tipo: los que no se quieren
        $tipos = array_filter(array_map([self::class, 'tipoClave'], explode(',', (string) ($f['tipo'] ?? ''))));
        if ($tipos) {
            $q->whereIn('r.tipo', array_values($tipos));
        }
        $fuera = array_filter(array_map([self::class, 'tipoClave'], explode(',', (string) ($f['sin_tipo'] ?? ''))));
        if ($fuera) {
            $q->whereNotIn('r.tipo', array_values($fuera));
        }
        if (($f['nivel'] ?? '') !== '' && ($n = Niveles::buscar(Buscador::sinAcentos((string) $f['nivel'])))) {
            $q->where('n.rating_minimo', $n['min']);
        }
        if (!empty($f['anio']) && ctype_digit((string) $f['anio'])) {
            $q->whereRaw("left(r.metadatos->>'fecha', 4) = ?", [(string) $f['anio']]);
        }
        if (!empty($f['eco'])) {
            $q->where('r.eco', strtoupper(substr((string) $f['eco'], 0, 3)));
        }
        if (($f['estatus'] ?? '') !== '') {
            $q->where('r.publicado', in_array(Buscador::sinAcentos((string) $f['estatus']), ['activo', 'true', '1', 'publicado'], true));
        }
        if (!empty($f['id_sede'])) {
            $q->where('r.id_sede', (int) $f['id_sede']);
        }
        self::texto($q, $f['q'] ?? null);
        return $q;
    }

    /**
     * Texto completo (SCRUM-28): cada palabra como prefijo ("ajed" encuentra "ajedrez"), sin acentos y en español.
     * También encuentra por parte del título y por código ECO exacto (B33).
     */
    public static function texto(Builder $q, ?string $texto): void
    {
        $palabras = array_values(array_filter(array_map(
            fn($p) => preg_replace('/[^a-z0-9ñ]/u', '', Buscador::sinAcentos($p)),
            Buscador::palabras($texto)
        )));
        if (!$palabras) {
            $q->orderByDesc('r.creado_en')->orderByDesc('r.id');
            return;
        }
        $tsquery = implode(' & ', array_map(fn($p) => $p . ':*', $palabras));
        $frase = implode(' ', $palabras);

        $q->where(function (Builder $w) use ($tsquery, $frase, $palabras) {
            $w->whereRaw("r.busqueda @@ to_tsquery('spanish', ?)", [$tsquery])
              ->orWhereRaw(Buscador::expresion('r.titulo') . " LIKE ? ESCAPE '!'", [Buscador::patron($frase)]);
            if (count($palabras) === 1 && preg_match('/^[a-e][0-9]{2}$/', $palabras[0])) {
                $w->orWhere('r.eco', strtoupper($palabras[0]));
            }
        });
        $q->orderByRaw("ts_rank(r.busqueda, to_tsquery('spanish', ?)) DESC", [$tsquery])->orderBy('r.titulo');
    }

    /** Lo que un alumno puede ver: publicado, de su sede o general, de su nivel o menor y para su edad. */
    public static function paraAlumno(int $idAlumno, array $f = []): Builder
    {
        $alumno = DB::table('core.perfil as pf')
            ->join('core.persona as pe', 'pe.id', '=', 'pf.id_persona')
            ->where('pf.id_persona', $idAlumno)->where('pf.tipo', 'alumno')
            ->selectRaw('pf.id_sede, pe.rating, pe.fecha_nacimiento')
            ->first();

        unset($f['estatus'], $f['id_sede']);
        $q = self::consulta($f)->where('r.publicado', true);
        $q->where(fn($w) => $w->whereNull('r.id_sede')->orWhere('r.id_sede', $alumno->id_sede ?? 0));
        $q->where(fn($w) => $w->whereNull('r.id_nivel_minimo')->orWhere('n.rating_minimo', '<=', (int) ($alumno->rating ?? 0)));
        if ($alumno && $alumno->fecha_nacimiento) {
            $q->where('r.edad_minima', '<=', \Carbon\Carbon::parse($alumno->fecha_nacimiento)->age);
        }
        return $q;
    }

    /** El personal sin rol global solo ve el material general y el de su sede. */
    public static function alcancePersonal(Builder $q): Builder
    {
        $global = filter_var(session('administrativo', false), FILTER_VALIDATE_BOOLEAN);
        if (!$global && session('id_sede')) {
            $q->where(fn($w) => $w->whereNull('r.id_sede')->orWhere('r.id_sede', (int) session('id_sede')));
        }
        return $q;
    }

    /** Una fila con el formato de las vistas. $contenido completo solo en el detalle. */
    public static function fila(object $r, ?array $contenido = null): array
    {
        $m = is_array($r->metadatos) ? $r->metadatos : (json_decode($r->metadatos ?? '{}', true) ?: []);
        $res = $contenido ?? (json_decode($r->resumen ?? '{}', true) ?: []);
        $archivo = $res['archivo'] ?? null;
        $nivel = $r->nivel_min !== null ? Niveles::dePuntaje((int) $r->nivel_min) : null;
        $palabras = $m['palabras_clave'] ?? [];

        $fila = [
            'id'            => (int) $r->id,
            'uid'           => $r->uid,
            'titulo'        => $r->titulo,
            'tipo'          => self::TIPOS[$r->tipo] ?? $r->tipo,
            'tipo_clave'    => $r->tipo,
            'creador'       => $m['creador'] ?? ($r->fuente ?? ''),
            'tema'          => $m['tema'] ?? '',
            'descripcion'   => $m['descripcion'] ?? '',
            'fecha'         => $m['fecha'] ?? substr((string) $r->creado_en, 0, 10),
            'formato'       => $archivo ? strtoupper($archivo['extension'] ?? '') : (isset($res['jugadas']) || isset($res['fen_final']) ? 'PGN' : ($m['url'] ?? null ? 'Enlace' : '')),
            'idioma'        => self::IDIOMAS[trim((string) $r->idioma)] ?? $r->idioma,
            'nivel'         => $nivel['nombre'] ?? null,
            'nivel_clave'   => $nivel['clave'] ?? null,
            'palabrasClave' => is_array($palabras) ? $palabras : array_filter(array_map('trim', explode(',', (string) $palabras))),
            'bytes'         => $archivo['bytes'] ?? null,
            'tamano'        => isset($archivo['bytes']) ? self::tamano((int) $archivo['bytes']) : '',
            'estatus'       => $r->publicado ? 'Activo' : 'Inactivo',
            'portada'       => $m['portada'] ?? 'bib-portada-' . (((int) $r->id - 1) % 6 + 1),
            'eco'           => $r->eco ? trim($r->eco) : null,
            'id_sede'       => $r->id_sede,
            'nombre_sede'   => $r->nombre_sede ?? null,
            'edad_minima'   => (int) $r->edad_minima,
            'fuente'        => $r->fuente,
            'licencia'      => $r->licencia,
            'url'           => $m['url'] ?? null,
            'tiene_archivo' => (bool) $archivo,
        ];

        if (in_array($r->tipo, ['partida', 'apertura', 'problema'], true) && isset($res['fen_final'])) {
            $fila += [
                'blancas'   => $m['blancas'] ?? null,
                'negras'    => $m['negras'] ?? null,
                'resultado' => $res['resultado'] ?? null,
                'evento'    => $m['evento'] ?? null,
                'apertura'  => $m['apertura'] ?? ($r->tipo === 'apertura' ? $r->titulo : null),
                'fen_final' => $res['fen_final'],
            ];
        }
        if ($contenido !== null) {
            unset($contenido['archivo']);   // la ruta interna del archivo no sale; se baja con URL firmada
            $fila['movimientos'] = $contenido['jugadas_es'] ?? [];
            $fila['jugadas'] = $contenido['jugadas'] ?? [];
            $fila['fens'] = $contenido['fens'] ?? [];
            $fila['pgn'] = $contenido['pgn'] ?? null;
            $fila['variantes'] = $contenido['variantes'] ?? [];
            $fila['sha256'] = $archivo['sha256'] ?? null;
        }
        return $fila;
    }

    /** Detalle completo de un recurso (con jugadas y posiciones). */
    public static function detalle(Builder $q, int $id): ?array
    {
        $r = (clone $q)->where('r.id', $id)->addSelect(DB::raw('r.contenido AS contenido_completo'))->first();
        if (!$r) return null;
        return self::fila($r, json_decode($r->contenido_completo ?? '{}', true) ?: []);
    }

    public static function tamano(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024) . ' KB';
        return $bytes . ' B';
    }
}
