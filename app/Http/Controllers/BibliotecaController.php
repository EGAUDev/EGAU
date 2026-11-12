<?php

namespace App\Http\Controllers;

use App\Services\Biblioteca;
use App\Services\BibliotecaEscritura;
use App\Services\Buscador;
use App\Services\DuplicadoException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * API de la biblioteca (SCRUM-27).
 *   Personal: /api/biblioteca/recursos...           (permisos biblioteca.ver / editar / eliminar)
 *   Alumno:   /api/biblioteca/alumno/...            (solo lo que le toca ver, SCRUM-47)
 *   Archivo:  /biblioteca/archivo/{uid}             (URL firmada que caduca, SCRUM-31)
 */
class BibliotecaController extends Controller
{
    private const FILTROS = ['q', 'tipo', 'sin_tipo', 'nivel', 'anio', 'eco', 'estatus', 'id_sede'];
    private const MINUTOS_ENLACE = 15;

    // ===================== PERSONAL =====================

    /** GET /api/biblioteca/recursos?q=&tipo=&nivel=&anio=&eco=&estatus=&page=&per_page= */
    public function listar(Request $request)
    {
        $q = Biblioteca::alcancePersonal(Biblioteca::consulta($request->only(self::FILTROS)));
        [$filas, $meta] = Buscador::obtener($q, $request);
        return response()->json(['data' => $filas->map(fn($r) => Biblioteca::fila($r))->values(), 'meta' => $meta, 'total' => $meta['total']]);
    }

    /** GET /api/biblioteca/filtros: años que tienen material (para el filtro de año). */
    public function filtros()
    {
        $anios = Biblioteca::alcancePersonal(Biblioteca::consulta())
            ->reorder()->whereRaw("r.metadatos->>'fecha' IS NOT NULL")
            ->select(DB::raw("DISTINCT left(r.metadatos->>'fecha', 4) AS anio"))->orderBy('anio')->pluck('anio');
        return response()->json(['anios' => $anios]);
    }

    /** GET /api/biblioteca/recursos/{id} */
    public function ver(int $id)
    {
        $r = Biblioteca::detalle(Biblioteca::alcancePersonal(Biblioteca::consulta()), $id);
        return $r ? response()->json($r) : response()->json(['error' => 'Recurso no encontrado.'], 404);
    }

    /** POST /api/biblioteca/recursos (multipart: campos + archivo o pgn) */
    public function crear(Request $request)
    {
        return $this->guardar($request, null);
    }

    /** POST /api/biblioteca/recursos/{id} (multipart, por eso no es PUT) */
    public function editar(Request $request, int $id)
    {
        if (!$this->existeParaPersonal($id)) {
            return response()->json(['error' => 'Recurso no encontrado.'], 404);
        }
        return $this->guardar($request, $id);
    }

    private function guardar(Request $request, ?int $id)
    {
        $maxKb = 200 * 1024;
        $v = Validator::make($request->all(), [
            'titulo'         => 'required|string|max:200',
            'tipo'           => 'required|string',
            'creador'        => 'nullable|string|max:200',
            'tema'           => 'nullable|string|max:200',
            'descripcion'    => 'nullable|string|max:2000',
            'fecha'          => 'nullable|date_format:Y-m-d',
            'nivel'          => 'nullable|string|max:20',
            'idioma'         => 'nullable|string|size:2',
            'palabras_clave' => 'nullable',
            'pgn'            => 'nullable|string|max:200000',
            'eco'            => ['nullable', 'regex:/^[A-Ea-e][0-9]{2}$/'],
            'url'            => 'nullable|url|max:200',
            'edad_minima'    => 'nullable|integer|min:3|max:99',
            'id_sede'        => 'nullable|integer|exists:pgsql.core.sede,id',
            'publicado'      => 'nullable|boolean',
            'archivo'        => "nullable|file|max:{$maxKb}",
        ], [
            'titulo.required' => 'El título es obligatorio.',
            'archivo.max'     => 'El archivo es demasiado grande.',
            'archivo.uploaded'=> 'El archivo no se pudo subir (revisa upload_max_filesize del servidor).',
        ]);
        $v->after(function ($v) use ($request) {
            if (!Biblioteca::tipoClave($request->input('tipo'))) {
                $v->errors()->add('tipo', 'Tipo inválido.');
            }
        });
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        // Un alumno o personal de sede solo puede guardar material general o de su sede
        $datos = $v->validated();
        if (!filter_var(session('administrativo', false), FILTER_VALIDATE_BOOLEAN) && !empty($datos['id_sede'])
            && (int) $datos['id_sede'] !== (int) session('id_sede')) {
            return response()->json(['errors' => ['id_sede' => ['Solo puedes publicar en tu sede.']]], 422);
        }

        try {
            $nuevoId = BibliotecaEscritura::guardar($id, $datos, $request->file('archivo'));
        } catch (DuplicadoException $e) {
            return response()->json(['errors' => ['archivo' => [$e->getMessage()]], 'id_existente' => $e->idExistente], 409);
        } catch (InvalidArgumentException $e) {
            $campo = str_contains($e->getMessage(), 'archivo') || str_contains($e->getMessage(), 'MB') ? 'archivo' : 'pgn';
            return response()->json(['errors' => [$campo => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'message' => $id ? 'Material actualizado.' : 'Material agregado.',
            'data'    => Biblioteca::detalle(Biblioteca::consulta(), $nuevoId),
        ], $id ? 200 : 201);
    }

    /** PATCH /api/biblioteca/recursos/{id}/estatus {publicado: bool} */
    public function estatus(Request $request, int $id)
    {
        if (!$this->existeParaPersonal($id)) {
            return response()->json(['error' => 'Recurso no encontrado.'], 404);
        }
        BibliotecaEscritura::cambiarEstatus($id, $request->boolean('publicado'));
        return response()->json(['message' => $request->boolean('publicado') ? 'Material publicado.' : 'Material ocultado.']);
    }

    /** DELETE /api/biblioteca/recursos/{id} */
    public function eliminar(int $id)
    {
        if (!$this->existeParaPersonal($id)) {
            return response()->json(['error' => 'Recurso no encontrado.'], 404);
        }
        BibliotecaEscritura::eliminar($id);
        return response()->json(['message' => 'Material eliminado.']);
    }

    /** POST /api/biblioteca/validar-pgn {pgn}: revisa el PGN antes de guardar (SCRUM-30). */
    public function validarPgn(Request $request)
    {
        try {
            $c = BibliotecaEscritura::contenidoPgn((string) $request->input('pgn', ''));
        } catch (InvalidArgumentException $e) {
            return response()->json(['valido' => false, 'error' => $e->getMessage()], 422);
        }
        return response()->json(['valido' => true, 'jugadas' => count($c['jugadas']), 'movimientos' => $c['jugadas_es'],
            'fen_final' => $c['fen_final'], 'resultado' => $c['resultado'], 'etiquetas' => $c['etiquetas']]);
    }

    /** GET /api/biblioteca/recursos/{id}/enlace: URLs firmadas para ver o descargar el archivo. */
    public function enlace(int $id)
    {
        return $this->enlaces(Biblioteca::alcancePersonal(Biblioteca::consulta()), $id);
    }

    private function existeParaPersonal(int $id): bool
    {
        return Biblioteca::alcancePersonal(Biblioteca::consulta())->where('r.id', $id)->exists();
    }

    // ===================== ALUMNO =====================

    /** GET /api/biblioteca/alumno/recursos: solo lo publicado de su sede y nivel. */
    public function alumnoListar(Request $request)
    {
        $q = Biblioteca::paraAlumno((int) session('id_persona'), $request->only(self::FILTROS));
        [$filas, $meta] = Buscador::obtener($q, $request);
        return response()->json(['data' => $filas->map(fn($r) => Biblioteca::fila($r))->values(), 'meta' => $meta, 'total' => $meta['total']]);
    }

    /** GET /api/biblioteca/alumno/recursos/{id} (con su progreso) */
    public function alumnoVer(int $id)
    {
        $idAlumno = (int) session('id_persona');
        $r = Biblioteca::detalle(Biblioteca::paraAlumno($idAlumno), $id);
        if (!$r) return response()->json(['error' => 'Recurso no encontrado.'], 404);
        $p = DB::table('biblioteca.progreso_alumno')->where(['id_alumno' => $idAlumno, 'id_recurso' => $id])->first();
        $r['progreso'] = $p ? ['favorito' => (bool) $p->favorito, 'marcador' => json_decode($p->marcador, true),
            'veces_abierto' => (int) $p->veces_abierto, 'ultima_consulta' => $p->ultima_consulta] : null;
        return response()->json($r);
    }

    /** POST /api/biblioteca/alumno/recursos/{id}/progreso {evento: abrir|avance, porcentaje?, pagina?, jugada?} */
    public function alumnoProgreso(Request $request, int $id)
    {
        $idAlumno = (int) session('id_persona');
        if (!Biblioteca::paraAlumno($idAlumno)->where('r.id', $id)->exists()) {
            return response()->json(['error' => 'Recurso no encontrado.'], 404);
        }
        $evento = $request->input('evento') === 'abrir' ? 'abrir' : 'avance';
        return response()->json(BibliotecaEscritura::progreso($idAlumno, $id, $evento,
            $request->only(['porcentaje', 'pagina', 'jugada', 'segundo'])));
    }

    /** PUT /api/biblioteca/alumno/recursos/{id}/favorito {favorito: bool} */
    public function alumnoFavorito(Request $request, int $id)
    {
        $idAlumno = (int) session('id_persona');
        if (!Biblioteca::paraAlumno($idAlumno)->where('r.id', $id)->exists()) {
            return response()->json(['error' => 'Recurso no encontrado.'], 404);
        }
        return response()->json(BibliotecaEscritura::progreso($idAlumno, $id, 'favorito', [], $request->boolean('favorito')));
    }

    /** GET /api/biblioteca/alumno/progreso: historial, favoritos y resumen ("Mi progreso"). */
    public function alumnoMiProgreso()
    {
        $idAlumno = (int) session('id_persona');
        $filas = Biblioteca::paraAlumno($idAlumno)
            ->join('biblioteca.progreso_alumno as p', fn($j) => $j->on('p.id_recurso', '=', 'r.id')->where('p.id_alumno', '=', $idAlumno))
            ->addSelect('p.favorito', 'p.marcador', 'p.veces_abierto', 'p.ultima_consulta')
            ->reorder('p.ultima_consulta', 'desc')
            ->limit(200)
            ->get();

        $data = $filas->map(function ($r) {
            $marcador = json_decode($r->marcador, true) ?: [];
            return Biblioteca::fila($r) + [
                'favorito'        => (bool) $r->favorito,
                'porcentaje'      => (float) ($marcador['porcentaje'] ?? 0),
                'marcador'        => $marcador,
                'veces_abierto'   => (int) $r->veces_abierto,
                'ultima_consulta' => $r->ultima_consulta,
            ];
        })->values();

        return response()->json([
            'data'    => $data,
            'resumen' => [
                'abiertos'    => $data->where('veces_abierto', '>', 0)->count(),
                'terminados'  => $data->where('porcentaje', '>=', 100)->count(),
                'favoritos'   => $data->where('favorito', true)->count(),
                'por_tipo'    => $data->groupBy('tipo')->map->count(),
            ],
        ]);
    }

    /** GET /api/biblioteca/alumno/recursos/{id}/enlace */
    public function alumnoEnlace(int $id)
    {
        return $this->enlaces(Biblioteca::paraAlumno((int) session('id_persona')), $id);
    }

    // ===================== ARCHIVO FIRMADO =====================

    private function enlaces($consulta, int $id)
    {
        $r = (clone $consulta)->where('r.id', $id)->select('r.uid', DB::raw("(r.contenido->'archivo') IS NOT NULL AS tiene"))->first();
        if (!$r || !$r->tiene) {
            return response()->json(['error' => 'Este material no tiene archivo.'], 404);
        }
        $expira = now()->addMinutes(self::MINUTOS_ENLACE);
        return response()->json([
            'ver'       => URL::temporarySignedRoute('biblioteca.archivo', $expira, ['uid' => $r->uid]),
            'descargar' => URL::temporarySignedRoute('biblioteca.archivo', $expira, ['uid' => $r->uid, 'descargar' => 1]),
            'expira'    => $expira->toIso8601String(),
        ]);
    }

    /** GET /biblioteca/archivo/{uid}?expires=&signature= (middleware signed) */
    public function archivo(Request $request, string $uid)
    {
        if (!preg_match('/^[0-9a-f-]{36}$/', $uid)) abort(404);
        $a = DB::table('biblioteca.recurso')->where('uid', $uid)->value(DB::raw("contenido->'archivo'"));
        $a = $a ? json_decode($a, true) : null;
        if (!$a || !Storage::disk(BibliotecaEscritura::DISCO)->exists($a['ruta'])) abort(404);

        $nombre = $a['nombre'] ?? basename($a['ruta']);
        $cabeceras = ['Content-Type' => $a['mime'], 'Cache-Control' => 'private, max-age=600', 'X-Content-Type-Options' => 'nosniff'];
        $ruta = Storage::disk(BibliotecaEscritura::DISCO)->path($a['ruta']);
        return $request->boolean('descargar')
            ? response()->download($ruta, $nombre, $cabeceras)
            : response()->file($ruta, $cabeceras + ['Content-Disposition' => 'inline; filename="' . addcslashes($nombre, '"\\') . '"']);
    }
}
