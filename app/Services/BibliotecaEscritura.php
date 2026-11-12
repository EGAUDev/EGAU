<?php
namespace App\Services;

use App\Services\Ajedrez\Pgn;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Altas, cambios y bajas del material (SCRUM-30, 31, 33) y el progreso del alumno (SCRUM-32).
 * Cada cambio corre en una transacción que primero indica quién lo hizo, para que el
 * trigger de auditoria.evento lo registre con su usuario e IP.
 */
class BibliotecaEscritura
{
    /** Archivos que se aceptan por extensión: tipo MIME y tamaño máximo en MB. */
    public const ARCHIVOS = [
        'pdf'  => ['mime' => ['application/pdf'], 'mb' => 50],
        'epub' => ['mime' => ['application/epub+zip', 'application/zip'], 'mb' => 50],
        'mp4'  => ['mime' => ['video/mp4'], 'mb' => 200],
        'webm' => ['mime' => ['video/webm'], 'mb' => 200],
    ];
    public const DISCO = 'local';

    /** Quién hace el cambio (solo dura la transacción actual). */
    public static function contextoAuditoria(?string $ip = null): void
    {
        DB::select("SELECT set_config('app.usuario_id', ?, true), set_config('app.ip', ?, true)", [
            (string) (session('id_usuario') ?? ''),
            (string) ($ip ?? request()?->ip() ?? ''),
        ]);
    }

    /** Valida un PGN y regresa el contenido que se guarda (jugadas, posiciones, FEN final). */
    public static function contenidoPgn(string $pgn): array
    {
        $p = Pgn::leer($pgn);
        $etiquetas = array_intersect_key($p['etiquetas'], array_flip(['Event', 'Site', 'Date', 'White', 'Black', 'Result', 'ECO', 'Opening', 'FEN', 'SetUp']));
        return [
            'pgn'        => Pgn::escribir($p['jugadas'], $etiquetas, $p['resultado']),
            'jugadas'    => $p['jugadas'],
            'jugadas_es' => $p['jugadas_es'],
            'fens'       => $p['fens'],
            'fen_final'  => $p['fen_final'],
            'resultado'  => $p['resultado'],
            'etiquetas'  => $etiquetas,
        ];
    }

    /**
     * Guarda el archivo con nombre = SHA-256 (así no se duplica) y regresa sus datos.
     * Lanza DuplicadoException si otro recurso ya tiene ese mismo archivo.
     */
    public static function guardarArchivo(UploadedFile $archivo, ?int $idRecurso = null): array
    {
        $ext = strtolower($archivo->getClientOriginalExtension());
        $regla = self::ARCHIVOS[$ext] ?? null;
        if (!$regla) {
            throw new InvalidArgumentException('Tipo de archivo no permitido. Usa PDF, EPUB, MP4 o WEBM.');
        }
        if (!in_array($archivo->getMimeType(), $regla['mime'], true)) {
            throw new InvalidArgumentException("El archivo no parece un {$ext} válido.");
        }
        if ($archivo->getSize() > $regla['mb'] * 1048576) {
            throw new InvalidArgumentException("El archivo pasa de {$regla['mb']} MB.");
        }

        $sha = hash_file('sha256', $archivo->getRealPath());
        $existe = DB::table('biblioteca.recurso')
            ->whereRaw("contenido->'archivo'->>'sha256' = ?", [$sha])
            ->when($idRecurso, fn($q) => $q->where('id', '!=', $idRecurso))
            ->first(['id', 'titulo']);
        if ($existe) {
            throw new DuplicadoException("Ese archivo ya está en la biblioteca: \"{$existe->titulo}\".", (int) $existe->id);
        }

        $ruta = 'biblioteca/' . substr($sha, 0, 2) . "/{$sha}.{$ext}";
        if (!Storage::disk(self::DISCO)->exists($ruta)) {
            Storage::disk(self::DISCO)->putFileAs(dirname($ruta), $archivo, basename($ruta));
        }
        return [
            'ruta'      => $ruta,
            'sha256'    => $sha,
            'extension' => $ext,
            'mime'      => $archivo->getMimeType(),
            'bytes'     => $archivo->getSize(),
            'nombre'    => mb_substr($archivo->getClientOriginalName(), 0, 150),
        ];
    }

    /** Alta (id null) o cambio de un recurso. $d ya viene validado por el controlador. */
    public static function guardar(?int $id, array $d, ?UploadedFile $archivo = null): int
    {
        $tipo = Biblioteca::tipoClave($d['tipo']) ?? 'libro';
        $anterior = $id ? DB::table('biblioteca.recurso')->where('id', $id)->first() : null;
        $contenido = $anterior ? (json_decode($anterior->contenido, true) ?: []) : [];
        $metadatos = $anterior ? (json_decode($anterior->metadatos, true) ?: []) : [];

        if (!empty($d['pgn'])) {
            $contenido = array_merge(array_intersect_key($contenido, ['archivo' => 1]), self::contenidoPgn($d['pgn']));
            $et = $contenido['etiquetas'];
            $d['blancas'] = $d['blancas'] ?? ($et['White'] ?? null);
            $d['negras']  = $d['negras'] ?? ($et['Black'] ?? null);
            $d['evento']  = $d['evento'] ?? ($et['Event'] ?? null);
            $d['eco']     = $d['eco'] ?? ($et['ECO'] ?? null);
        } elseif (in_array($tipo, ['partida', 'apertura'], true) && empty($contenido['jugadas'])) {
            throw new InvalidArgumentException('Una partida o apertura necesita su PGN.');
        }

        if ($archivo) {
            $contenido['archivo'] = self::guardarArchivo($archivo, $id);
        }

        foreach (['creador', 'tema', 'descripcion', 'fecha', 'blancas', 'negras', 'evento', 'apertura', 'url', 'portada'] as $k) {
            if (array_key_exists($k, $d)) {
                $metadatos[$k] = ($d[$k] === null || $d[$k] === '') ? null : mb_substr(trim((string) $d[$k]), 0, $k === 'descripcion' ? 2000 : 200);
            }
        }
        if (array_key_exists('palabras_clave', $d)) {
            $pc = is_array($d['palabras_clave']) ? $d['palabras_clave'] : explode(',', (string) $d['palabras_clave']);
            $metadatos['palabras_clave'] = array_values(array_unique(array_filter(array_map(fn($p) => mb_substr(trim($p), 0, 40), $pc))));
        }
        $metadatos = array_filter($metadatos, fn($v) => $v !== null && $v !== []);

        $nivel = !empty($d['nivel']) ? Niveles::buscar(Buscador::sinAcentos($d['nivel'])) : null;
        $fila = [
            'tipo'            => $tipo,
            'titulo'          => mb_substr(trim($d['titulo']), 0, 200),
            'eco'             => !empty($d['eco']) && preg_match('/^[A-E][0-9]{2}$/', strtoupper($d['eco'])) ? strtoupper($d['eco']) : null,
            'id_nivel_minimo' => $nivel ? DB::table('academico.nivel')->where('rating_minimo', $nivel['min'])->value('id') : null,
            'edad_minima'     => max(3, min(99, (int) ($d['edad_minima'] ?? ($anterior->edad_minima ?? 5)))),
            'idioma'          => array_key_exists($d['idioma'] ?? '', Biblioteca::IDIOMAS) ? $d['idioma'] : ($anterior->idioma ?? 'es'),
            'fuente'          => isset($d['fuente']) ? mb_substr($d['fuente'], 0, 120) : ($anterior->fuente ?? null),
            'licencia'        => isset($d['licencia']) ? mb_substr($d['licencia'], 0, 60) : ($anterior->licencia ?? null),
            'contenido'       => json_encode($contenido, JSON_UNESCAPED_UNICODE),
            'metadatos'       => json_encode($metadatos ?: new \stdClass(), JSON_UNESCAPED_UNICODE),
            'publicado'       => array_key_exists('publicado', $d) ? (bool) $d['publicado'] : (bool) ($anterior->publicado ?? true),
            'id_sede'         => array_key_exists('id_sede', $d) ? ($d['id_sede'] ? (int) $d['id_sede'] : null) : ($anterior->id_sede ?? null),
        ];

        return DB::transaction(function () use ($id, $fila) {
            self::contextoAuditoria();
            if ($id) {
                DB::table('biblioteca.recurso')->where('id', $id)->update($fila);
                return $id;
            }
            return (int) DB::table('biblioteca.recurso')->insertGetId($fila + [
                'slug'       => self::slugLibre($fila['titulo']),
                'creado_por' => session('id_usuario'),
            ]);
        });
    }

    public static function cambiarEstatus(int $id, bool $publicado): void
    {
        DB::transaction(function () use ($id, $publicado) {
            self::contextoAuditoria();
            DB::table('biblioteca.recurso')->where('id', $id)->update(['publicado' => $publicado]);
        });
    }

    /** Borra el recurso; el archivo se borra solo si ningún otro lo usa. */
    public static function eliminar(int $id): void
    {
        $ruta = DB::table('biblioteca.recurso')->where('id', $id)->value(DB::raw("contenido->'archivo'->>'ruta'"));
        DB::transaction(function () use ($id) {
            self::contextoAuditoria();
            DB::table('biblioteca.recurso')->where('id', $id)->delete();   // progreso_alumno se borra en cascada
        });
        if ($ruta && !DB::table('biblioteca.recurso')->whereRaw("contenido->'archivo'->>'ruta' = ?", [$ruta])->exists()) {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }

    public static function slugLibre(string $titulo, string $prefijo = ''): string
    {
        $base = mb_substr(trim($prefijo . Str::slug($titulo), '-') ?: 'recurso', 0, 200);
        $slug = $base;
        for ($i = 2; DB::table('biblioteca.recurso')->where('slug', $slug)->exists(); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    /**
     * Progreso del alumno (SCRUM-32).
     * $evento 'abrir' suma una apertura; 'avance' guarda hasta dónde llegó
     * (porcentaje leído, página o jugada vista). El porcentaje nunca baja.
     */
    public static function progreso(int $idAlumno, int $idRecurso, string $evento, array $marcador = [], ?bool $favorito = null): array
    {
        $marcador = array_intersect_key($marcador, array_flip(['porcentaje', 'pagina', 'jugada', 'segundo']));
        $marcador = array_map(fn($v) => is_numeric($v) ? max(0, min(100000, (float) $v)) : null, $marcador);
        if (isset($marcador['porcentaje'])) $marcador['porcentaje'] = min(100, $marcador['porcentaje']);
        $marcador = array_filter($marcador, fn($v) => $v !== null);

        DB::statement("
            INSERT INTO biblioteca.progreso_alumno AS p (id_alumno, id_recurso, favorito, marcador, veces_abierto, ultima_consulta)
            VALUES (?, ?, COALESCE(?, false), ?::jsonb, ?, now())
            ON CONFLICT (id_alumno, id_recurso) DO UPDATE SET
              favorito        = COALESCE(?, p.favorito),
              veces_abierto   = p.veces_abierto + EXCLUDED.veces_abierto,
              ultima_consulta = now(),
              marcador        = p.marcador || EXCLUDED.marcador || jsonb_build_object('porcentaje',
                                  GREATEST(COALESCE((p.marcador->>'porcentaje')::numeric, 0), COALESCE((EXCLUDED.marcador->>'porcentaje')::numeric, 0)))",
            [$idAlumno, $idRecurso, $favorito, json_encode($marcador ?: new \stdClass()), $evento === 'abrir' ? 1 : 0, $favorito]
        );

        $p = DB::table('biblioteca.progreso_alumno')->where(['id_alumno' => $idAlumno, 'id_recurso' => $idRecurso])->first();
        return [
            'favorito'        => (bool) $p->favorito,
            'marcador'        => json_decode($p->marcador, true),
            'veces_abierto'   => (int) $p->veces_abierto,
            'ultima_consulta' => $p->ultima_consulta,
        ];
    }
}
