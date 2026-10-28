<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\EscrituraCore;
use App\Services\LecturaCore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function registrarUsuario(Request $request)
    {
        // Validar el tipo de usuario primero
        $validatorTipo = Validator::make($request->all(), [
            'tipo_usuario' => 'required|string|in:alumno,profesor,personal',
        ]);

        if ($validatorTipo->fails()) {
            return response()->json(['errors' => $validatorTipo->errors()], 422);
        }

        $tipo = $request->tipo_usuario;
        $sedes = DB::table('core.sede')->pluck('id');

        $rules = [
            'nombre' => 'required|string|max:50',
            'apellido_p' => 'required|string|max:50',
            'email' => 'required|email|max:100',
            'contraseña' => 'required|string|min:6',
            'telefono' => 'nullable|string|max:20',
            'estado_residencia' => 'required|string|max:50',
            'ciudad' => 'required|string|max:50',
            'calle' => 'required|string|max:255',
            'codigo_postal' => 'required|integer',
            'genero' => 'required|string|in:f,m,o,F,M,O',
            'fecha_nacimiento' => 'required|date',
        ];

        // Definir reglas específicas
        if ($tipo === 'alumno') {
            $rules['id_tutor'] = 'nullable|integer';
            $rules['id_sede'] = ['required', 'integer', Rule::in($sedes)];
        } elseif ($tipo === 'profesor') {
            $rules['id_sede'] = ['required', 'integer', Rule::in($sedes)];
        } elseif ($tipo === 'personal') {
            $rules['id_rol'] = ['required', 'integer', Rule::in(DB::table('core.rol')->where('es_sistema', false)->pluck('id'))];
            $rules['id_sede'] = ['nullable', 'integer', Rule::in($sedes)];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (EscrituraCore::correoOcupado($request->email)) {
            return response()->json(['errors' => ['email' => ['Ese correo ya tiene una cuenta en el sistema.']]], 422);
        }

        $datos = $request->only([
            'nombre', 'apellido_p', 'apellido_m', 'estado_residencia', 'ciudad', 'calle', 'codigo_postal',
            'genero', 'fecha_nacimiento', 'telefono', 'email', 'id_sede', 'id_rol',
        ]);
        $datos['estatus'] = $request->input('estatus', true);
        if ($tipo === 'alumno') {
            $datos['puntaje'] = $request->puntos ?? 0;
        }

        $id = DB::transaction(function () use ($tipo, $datos, $request) {
            $id = EscrituraCore::persona($tipo, null, $datos, $request->input('contraseña'));

            if ($tipo === 'alumno') {
                if ($request->filled('tu_nombre')) {
                    EscrituraCore::tutor($id, [
                        'nombre' => $request->tu_nombre, 'apellido_p' => $request->tu_ap_paterno,
                        'apellido_m' => $request->tu_ap_materno, 'parentesco' => $request->tu_parentesco,
                        'telefono' => $request->tu_telefono, 'email' => $request->tu_correo,
                    ]);
                } elseif ($request->filled('id_tutor')) {
                    EscrituraCore::ligarTutor($id, (int) $request->id_tutor);
                }
            }
            return $id;
        });

        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'usuario' => DB::query()->fromSub(LecturaCore::personas($tipo), $tipo)->where("id_{$tipo}", $id)->first(),
        ], 201);
    }

    public function editarUsuario(Request $request, $tipo, $id)
    {
        if (!in_array($tipo, ['alumno', 'profesor', 'personal'])) {
            return response()->json(['error' => 'Tipo de usuario inválido'], 400);
        }

        $id = (int) $id;
        if (!DB::table('core.perfil')->where('id_persona', $id)->where('tipo', $tipo)->exists()) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $sedes = DB::table('core.sede')->pluck('id');
        $rules = [
            'nombre' => 'sometimes|required|string|max:50',
            'apellido_p' => 'sometimes|required|string|max:50',
            'email' => 'sometimes|required|email|max:100',
            'contraseña' => 'nullable|string|min:6',
            'telefono' => 'nullable|string|max:20',
            'estado_residencia' => 'sometimes|required|string|max:50',
            'ciudad' => 'sometimes|required|string|max:50',
            'calle' => 'sometimes|required|string|max:255',
            'codigo_postal' => 'sometimes|required|integer',
            'genero' => 'sometimes|required|string|in:f,m,o,F,M,O',
            'fecha_nacimiento' => 'sometimes|required|date'
        ];

        if ($tipo === 'alumno') {
            $rules['id_tutor'] = 'nullable|integer';
            $rules['id_sede'] = ['nullable', 'integer', Rule::in($sedes)];
            $rules['puntaje'] = 'nullable|integer';
        } elseif ($tipo === 'profesor') {
            $rules['id_sede'] = ['nullable', 'integer', Rule::in($sedes)];
            $rules['puntaje'] = 'nullable|integer';
        } elseif ($tipo === 'personal') {
            $rules['id_rol'] = ['nullable', 'integer', Rule::in(DB::table('core.rol')->where('es_sistema', false)->pluck('id'))];
            $rules['id_sede'] = ['nullable', 'integer', Rule::in($sedes)];
        }

        // El formulario de alumnos manda el puntaje como "puntos" (igual que en el alta)
        if (in_array($tipo, ['alumno', 'profesor']) && !$request->has('puntaje') && $request->has('puntos')) {
            $request->merge(['puntaje' => $request->input('puntos')]);
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->filled('email') && EscrituraCore::correoOcupado($request->email, $id)) {
            return response()->json(['errors' => ['email' => ['Ese correo ya tiene una cuenta en el sistema.']]], 422);
        }

        $campos = [
            'nombre', 'apellido_p', 'apellido_m', 'estado_residencia', 'ciudad', 'calle', 'codigo_postal',
            'genero', 'fecha_nacimiento', 'telefono', 'email', 'estatus',
        ];
        if ($tipo === 'personal') {
            $campos[] = 'id_rol';
        } else {
            $campos[] = 'puntaje';
        }
        // Un campo vacío del formulario no borra la sede
        if ($request->filled('id_sede')) {
            $campos[] = 'id_sede';
        }
        $datos = $request->only($campos);

        DB::transaction(function () use ($tipo, $id, $datos, $request) {
            EscrituraCore::persona($tipo, $id, $datos, $request->filled('contraseña') ? $request->input('contraseña') : null);
            if ($tipo === 'alumno' && $request->has('id_tutor')) {
                EscrituraCore::ligarTutor($id, $request->filled('id_tutor') ? (int) $request->id_tutor : null);
            }
        });

        return response()->json([
            'message' => ucfirst($tipo) . ' actualizado exitosamente',
            'usuario' => DB::query()->fromSub(LecturaCore::personas($tipo), $tipo)->where("id_{$tipo}", $id)->first(),
        ]);
    }

    public function eliminarUsuario($tipo, $id)
    {
        if (!in_array($tipo, ['alumno', 'profesor', 'personal'])) {
            return response()->json(['error' => 'Tipo de usuario inválido'], 400);
        }

        if (!DB::table('core.perfil')->where('id_persona', (int) $id)->where('tipo', $tipo)->exists()) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        try {
            DB::transaction(fn () => EscrituraCore::eliminarPersona($tipo, (int) $id));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23503') {
                return response()->json([
                    'error' => 'No se puede eliminar porque tiene registros relacionados (grupos, pagos, inscripciones...). Puedes darlo de baja cambiando su estatus a inactivo.'
                ], 409);
            }
            throw $e;
        }

        return response()->json([
            'message' => ucfirst($tipo) . ' eliminado exitosamente'
        ]);
    }

    // -------------------------------------------------------
    // GET /admin/obtener/{tipo}/{id}
    // Devuelve un registro fresco de la DB para el modal editar
    // -------------------------------------------------------
    public function obtenerUsuario($tipo, $id)
    {
        if (!in_array($tipo, ['alumno', 'profesor', 'personal'])) {
            return response()->json(['error' => 'Tipo inválido'], 400);
        }

        $usuario = DB::query()->fromSub(LecturaCore::personas($tipo), $tipo)->where("id_{$tipo}", (int) $id)->first();

        // El personal guarda su sede aparte (usuariosede)
        if ($usuario && $tipo === 'personal') {
            $sede = DB::query()->fromSub(LecturaCore::sedesPersonal(), 'us')
                ->joinSub(LecturaCore::sedes(), 'sede', 'us.id_sede', '=', 'sede.id_sede')
                ->where('us.id_personal', (int) $id)
                ->orderBy('us.fecha_asignacion')->orderBy('us.id_sede')
                ->select('us.id_sede', 'sede.nombre as nombre_sede')->first();
            $usuario->id_sede = $sede->id_sede ?? null;
            $usuario->nombre_sede = $sede->nombre_sede ?? null;
        }

        if (!$usuario) {
            return response()->json(['error' => 'No encontrado'], 404);
        }

        return response()->json($usuario);
    }

    // -------------------------------------------------------
    // GET /admin/buscar/{tipo}?q=&id_sede=&estatus=&nivel=&id_rol=
    // Devuelve filas filtradas en JSON para re-render de tabla
    // -------------------------------------------------------
    public function buscarUsuarios(Request $request, $tipo)
    {
        if (!in_array($tipo, ['alumno', 'profesor', 'personal'])) {
            return response()->json(['error' => 'Tipo inválido'], 400);
        }

        $q = $request->query('q', '');
        $idSede = $request->query('id_sede', '');
        $estatus = $request->query('estatus', '');
        $nivel = $request->query('nivel', '');   // solo alumnos
        $idRol = $request->query('id_rol', '');  // solo personal

        $query = match ($tipo) {
            'alumno' => DB::query()->fromSub(LecturaCore::personas('alumno'), 'alumno')
                ->leftJoinSub(LecturaCore::sedes(), 'sede', 'alumno.id_sede', '=', 'sede.id_sede')
                ->select('alumno.*', 'sede.nombre as nombre_sede')
                ->orderBy('alumno.id_alumno', 'asc'),
            'profesor' => DB::query()->fromSub(LecturaCore::personas('profesor'), 'profesor')
                ->leftJoinSub(LecturaCore::sedes(), 'sede', 'profesor.id_sede', '=', 'sede.id_sede')
                ->select('profesor.*', 'sede.nombre as nombre_sede')
                ->orderBy('profesor.id_profesor', 'asc'),
            'personal' => DB::query()->fromSub(LecturaCore::personas('personal'), 'personal')
                ->leftJoinSub(LecturaCore::roles(), 'rol', 'personal.id_rol', '=', 'rol.id_rol')
                ->leftJoinSub(LecturaCore::sedesPersonal(), 'usuariosede', 'personal.id_personal', '=', 'usuariosede.id_personal')
                ->leftJoinSub(LecturaCore::sedes(), 'sede', 'usuariosede.id_sede', '=', 'sede.id_sede')
                ->select('personal.*', 'rol.nombre as nombre_rol', 'usuariosede.id_sede', 'sede.nombre as nombre_sede')
                ->orderBy('personal.id_personal', 'asc'),
        };

        if ($q !== '') {
            $query->where(function ($sub) use ($q, $tipo) {
                $tabla = $tipo === 'personal' ? 'personal' : $tipo;
                $pk = "id_{$tipo}";

                // ID exacto
                if (is_numeric($q)) {
                    $sub->orWhere("{$tabla}.{$pk}", (int) $q);
                }

                // Nombre, apellidos, email
                $sub->orWhereRaw("LOWER({$tabla}.nombre) LIKE ?", ["%{$q}%"])
                    ->orWhereRaw("LOWER({$tabla}.apellido_p) LIKE ?", ["%{$q}%"])
                    ->orWhereRaw("LOWER({$tabla}.apellido_m) LIKE ?", ["%{$q}%"])
                    ->orWhereRaw("LOWER({$tabla}.email) LIKE ?", ["%{$q}%"]);

                // Sede (solo alumno y profesor)
                if (in_array($tipo, ['alumno', 'profesor'])) {
                    $sub->orWhereRaw("LOWER(sede.nombre) LIKE ?", ["%{$q}%"]);
                }

                // Rol (solo personal)
                if ($tipo === 'personal') {
                    $sub->orWhereRaw("LOWER(rol.nombre) LIKE ?", ["%{$q}%"]);
                }

                // Estatus
                if (in_array($q, ['activo', 'inactivo'])) {
                    $esActivo = $q === 'activo';
                    $sub->orWhere("{$tabla}.estatus", $esActivo);
                }

                // Nivel (solo alumnos, basado en puntaje — piezas de ajedrez)
                if ($tipo === 'alumno') {
                    if (str_contains('peón', $q) || str_contains('peon', $q)) {
                        $sub->orWhere("{$tabla}.puntaje", '<', 150);
                    } elseif (str_contains('caballo', $q)) {
                        $sub->orWhereBetween("{$tabla}.puntaje", [150, 399]);
                    } elseif (str_contains('alfil', $q)) {
                        $sub->orWhereBetween("{$tabla}.puntaje", [400, 799]);
                    } elseif (str_contains('torre', $q)) {
                        $sub->orWhereBetween("{$tabla}.puntaje", [800, 1499]);
                    } elseif (str_contains('reina', $q)) {
                        $sub->orWhereBetween("{$tabla}.puntaje", [1500, 2999]);
                    } elseif (str_contains('rey', $q)) {
                        $sub->orWhere("{$tabla}.puntaje", '>=', 3000);
                    }
                }
            });
        }

        if ($idSede !== '') {
            if ($tipo === 'alumno')
                $query->where('alumno.id_sede', (int) $idSede);
            elseif ($tipo === 'profesor')
                $query->where('profesor.id_sede', (int) $idSede);
            elseif ($tipo === 'personal')
                $query->where('usuariosede.id_sede', (int) $idSede);
        }
        // Filtro estatus
        if ($estatus !== '') {
            $esActivo = in_array($estatus, ['activo', 'true', '1'], true);
            $tabla = $tipo === 'personal' ? 'personal' : $tipo;
            $query->where("{$tabla}.estatus", $esActivo);
        }

        // Filtro nivel (alumnos: basado en puntaje — piezas de ajedrez)
        if ($tipo === 'alumno' && $nivel !== '') {
            match ($nivel) {
                'peon' => $query->where('puntaje', '<', 150),
                'caballo' => $query->whereBetween('puntaje', [150, 399]),
                'alfil' => $query->whereBetween('puntaje', [400, 799]),
                'torre' => $query->whereBetween('puntaje', [800, 1499]),
                'reina' => $query->whereBetween('puntaje', [1500, 2999]),
                'rey' => $query->where('puntaje', '>=', 3000),
                default => null,
            };
        }

        // Filtro rol (personal)
        if ($tipo === 'personal' && $idRol !== '') {
            $query->where('personal.id_rol', (int) $idRol);
        }

        $resultados = $query->get();

        // Formatear respuesta para el frontend
        $filas = $resultados->map(function ($r) use ($tipo) {
            $base = [
                'id' => $r->{"id_{$tipo}"},
                'nombre' => trim("{$r->nombre} {$r->apellido_p} " . ($r->apellido_m ?? '')),
                'email' => $r->email,
                'estatus' => $r->estatus,
                'info' => (array) $r,
            ];

            if ($tipo === 'alumno') {
                $edad = \Carbon\Carbon::parse($r->fecha_nacimiento)->age;
                $puntaje = (int) ($r->puntaje ?? 0);
                $nivel = match (true) {
                    $puntaje >= 3000 => 'Rey',
                    $puntaje >= 1500 => 'Reina',
                    $puntaje >= 800 => 'Torre',
                    $puntaje >= 400 => 'Alfil',
                    $puntaje >= 150 => 'Caballo',
                    default => 'Peón',
                };
                $base['edad'] = $edad;
                $base['nivel'] = $nivel;
                $base['id_sede'] = $r->id_sede;
            } elseif ($tipo === 'profesor') {
                $base['id_sede'] = $r->id_sede;
                $base['puntaje'] = $r->puntaje;
            } elseif ($tipo === 'personal') {
                $base['id_rol'] = $r->id_rol;
                $base['nombre_rol'] = $r->nombre_rol ?? '';
            }

            return $base;
        });

        return response()->json(['total' => $filas->count(), 'data' => $filas]);
    }

    // ===================== SEDES =====================

    public function buscarSedes(Request $request)
    {
        $q = $request->get('q', '');
        $estado = $request->get('estado_residencia', '');

        $query = DB::query()->fromSub(LecturaCore::sedes(), 'sede');

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->whereRaw('LOWER(nombre) LIKE ?', ['%' . strtolower($q) . '%'])
                    ->orWhereRaw('LOWER(ciudad) LIKE ?', ['%' . strtolower($q) . '%'])
                    ->orWhereRaw('LOWER(calle) LIKE ?', ['%' . strtolower($q) . '%']);
            });
        }

        if ($estado) {
            $query->whereRaw('LOWER(estado_residencia) = ?', [strtolower($estado)]);
        }

        $sedes = $query->orderBy('id_sede')->get();

        return response()->json([
            'data' => $sedes->map(fn($s) => [
                'id' => $s->id_sede,
                'nombre' => $s->nombre,
                'estado_residencia' => $s->estado_residencia,
                'ciudad' => $s->ciudad,
                'codigo_postal' => $s->codigo_postal,
                'calle' => $s->calle,
                'telefono' => $s->telefono,
                'email' => $s->email,
                'estatus' => $s->estatus,
            ]),
            'total' => $sedes->count(),
        ]);
    }

    public function registrarSede(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'nombre' => 'required|string|max:50',
            'estado_residencia' => 'required|string|max:50',
            'ciudad' => 'required|string|max:50',
            'codigo_postal' => 'required|digits:5',
            'calle' => 'required|string|max:50',
            'telefono' => 'required|digits:10',
            'email' => 'required|email|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $id = DB::transaction(fn () => EscrituraCore::sede(null, $request->only([
            'nombre', 'estado_residencia', 'ciudad', 'codigo_postal', 'calle', 'telefono', 'email',
        ]) + ['estatus' => true]));

        return response()->json(['message' => 'Sede registrada correctamente.', 'id' => $id]);
    }

    public function obtenerSede($id)
    {
        $sede = DB::query()->fromSub(LecturaCore::sedes(), 'sede')->where('id_sede', (int) $id)->first();
        if (!$sede)
            return response()->json(['error' => 'Sede no encontrada.'], 404);

        return response()->json([
            'id' => $sede->id_sede,
            'nombre' => $sede->nombre,
            'estado_residencia' => $sede->estado_residencia,
            'ciudad' => $sede->ciudad,
            'codigo_postal' => $sede->codigo_postal,
            'calle' => $sede->calle,
            'telefono' => $sede->telefono,
            'email' => $sede->email,
            'estatus' => $sede->estatus,
        ]);
    }

    public function editarSede(Request $request, $id)
    {
        if (!DB::table('core.sede')->where('id', (int) $id)->exists())
            return response()->json(['error' => 'Sede no encontrada.'], 404);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'nombre' => 'required|string|max:50',
            'estado_residencia' => 'required|string|max:50',
            'ciudad' => 'required|string|max:50',
            'codigo_postal' => 'required|digits:5',
            'calle' => 'required|string|max:50',
            'telefono' => 'required|digits:10',
            'email' => 'required|email|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::transaction(fn () => EscrituraCore::sede((int) $id, $request->only([
            'nombre', 'estado_residencia', 'ciudad', 'codigo_postal', 'calle', 'telefono', 'email',
        ])));

        return response()->json(['message' => 'Sede actualizada correctamente.']);
    }

    public function eliminarSede($id)
    {
        if (!DB::table('core.sede')->where('id', (int) $id)->exists())
            return response()->json(['error' => 'Sede no encontrada.'], 404);

        try {
            DB::transaction(fn () => EscrituraCore::eliminarSede((int) $id));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23503') {
                return response()->json([
                    'error' => 'No se puede eliminar la sede porque tiene alumnos, profesores, personal o grupos asignados.'
                ], 409);
            }
            throw $e;
        }
        return response()->json(['message' => 'Sede eliminada correctamente.']);
    }

    // =========================================================
    // GRUPOS
    // =========================================================

    public function buscarGrupos(Request $request)
    {
        $q = $request->query('q', '');
        $idProfesor = $request->query('id_profesor', '');

        $grupos = DB::query()->fromSub(LecturaCore::grupos(), 'grupo')
            ->joinSub(LecturaCore::cursos(), 'curso',    'grupo.id_curso',    '=', 'curso.id_curso')
            ->joinSub(LecturaCore::personas('profesor'), 'profesor', 'grupo.id_profesor', '=', 'profesor.id_profesor')
            ->selectRaw("
                grupo.nombre,
                grupo.id_grupo,
                grupo.codigo_grupo,
                grupo.periodo,
                grupo.fecha_inicio,
                grupo.fecha_fin,
                grupo.cupo_maximo,
                grupo.estatus,
                grupo.id_curso,
                grupo.id_profesor,
                curso.nivel,
                curso.nombre AS nombre_curso,
                profesor.nombre AS prof_nombre,
                profesor.apellido_p AS prof_apellido,
                grupo.cupo_actual AS inscritos
            ")
            ->when($q !== '', function ($query) use ($q) {
                $ql = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($ql) {
                    $sub->whereRaw('LOWER(grupo.codigo_grupo) LIKE ?', [$ql])
                        ->orWhereRaw('LOWER(curso.nivel::text) LIKE ?',     [$ql])
                        ->orWhereRaw('LOWER(profesor.nombre) LIKE ?', [$ql])
                        ->orWhereRaw('LOWER(curso.nombre) LIKE ?',    [$ql]);
                });
            })
          ->when($idProfesor !== '', fn($q) => $q->where('grupo.id_profesor', (int) $idProfesor))
            ->orderBy('grupo.id_grupo')
            ->get();

        $ids = $grupos->pluck('id_grupo')->toArray();
        $horariosPorGrupo = DB::query()->fromSub(LecturaCore::horarios(), 'horario')
            ->whereIn('id_grupo', $ids)
            ->orderBy('id_horario')
            ->get()
            ->groupBy('id_grupo');

        $result = $grupos->map(function ($g) use ($horariosPorGrupo) {
            $hList = collect($horariosPorGrupo->get($g->id_grupo, []));
            return [
                'id_grupo'        => $g->id_grupo,
                'codigo_grupo'    => $g->codigo_grupo,
                'nombre' => $g->nombre,
                'periodo'         => $g->periodo,
                'nivel'           => $g->nivel,
                'nombre_curso'    => $g->nombre_curso,
                'id_curso'        => $g->id_curso,
                'id_profesor'     => $g->id_profesor,
                'nombre_profesor' => trim($g->prof_nombre . ' ' . $g->prof_apellido),
                'inscritos'       => (int) $g->inscritos,
                'cupo_maximo'     => (int) $g->cupo_maximo,
                'fecha_inicio'    => $g->fecha_inicio,
                'fecha_fin'       => $g->fecha_fin,
                'estatus'         => (bool) $g->estatus,
                'horarios'        => $hList->map(fn($h) => [
                    'id_horario'  => $h->id_horario,
                    'dia_semana'  => (int) $h->dia_semana,
                    'hora_inicio' => substr($h->hora_inicio, 0, 5),
                    'hora_fin'    => substr($h->hora_fin, 0, 5),
                    'ubicacion'   => $h->ubicacion ?? '',
                ])->values(),
            ];
        });

        return response()->json(['data' => $result]);
    }

    /** La fecha de inicio del grupo tiene que caer dentro del periodo elegido. */
    private static function revisarPeriodo($validator, Request $request): void
    {
        if (!$request->filled('periodo') || !$request->filled('fecha_inicio') || $validator->errors()->isNotEmpty()) {
            return;
        }
        $cuadra = DB::table('academico.periodo')->where('clave', $request->periodo)
            ->whereRaw('vigencia @> ?::date', [$request->fecha_inicio])->exists();
        if (!$cuadra) {
            $validator->errors()->add('periodo', 'La fecha de inicio no cae dentro del periodo ' . $request->periodo . '.');
        }
    }

    public function registrarGrupo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_curso'     => 'required|integer',
            'id_profesor'  => 'required|integer',
            'codigo_grupo' => 'required|string|max:20',
            'nombre'       => 'nullable|string|max:100',
            'periodo'      => ['nullable', 'string', Rule::in(DB::table('academico.periodo')->pluck('clave'))],
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after:fecha_inicio',
            'cupo_maximo'  => 'required|integer|min:1',
            'horarios'     => 'required|array|min:1',
        ], ['periodo.in' => 'Elige uno de los periodos de la lista.']);
        $validator->after(fn ($v) => self::revisarPeriodo($v, $request));
        if ($validator->fails())
            return response()->json(['errors' => $validator->errors()], 422);

        $id = DB::transaction(fn () => EscrituraCore::grupo(null, $request->only([
            'id_curso', 'id_profesor', 'codigo_grupo', 'nombre', 'periodo', 'fecha_inicio', 'fecha_fin', 'cupo_maximo',
        ]) + ['estatus' => $request->input('estatus', true)], $request->horarios));

        return response()->json(['message' => 'Grupo creado correctamente.', 'id' => $id]);
    }

    public function obtenerGrupo($id)
    {
        $g = DB::query()->fromSub(LecturaCore::grupos(), 'grupo')
            ->joinSub(LecturaCore::cursos(), 'curso',    'grupo.id_curso',    '=', 'curso.id_curso')
            ->joinSub(LecturaCore::personas('profesor'), 'profesor', 'grupo.id_profesor', '=', 'profesor.id_profesor')
            ->selectRaw("
                grupo.nombre,
                grupo.id_grupo, grupo.codigo_grupo, grupo.periodo,
                grupo.fecha_inicio, grupo.fecha_fin, grupo.cupo_maximo, grupo.estatus,
                grupo.id_curso, grupo.id_profesor,
                curso.nivel, curso.nombre AS nombre_curso,
                profesor.nombre AS prof_nombre, profesor.apellido_p AS prof_apellido,
                grupo.cupo_actual AS inscritos
            ")
            ->where('grupo.id_grupo', (int) $id)
            ->first();

        if (!$g) return response()->json(['error' => 'Grupo no encontrado.'], 404);

        $horarios = DB::query()->fromSub(LecturaCore::horarios(), 'horario')
            ->where('id_grupo', (int) $id)
            ->orderBy('id_horario')
            ->get()
            ->map(fn($h) => [
                'id_horario'  => $h->id_horario,
                'dia_semana'  => (int) $h->dia_semana,
                'hora_inicio' => substr($h->hora_inicio, 0, 5),
                'hora_fin'    => substr($h->hora_fin, 0, 5),
                'ubicacion'   => $h->ubicacion ?? '',
            ]);

        return response()->json([
            'id_grupo'        => $g->id_grupo,
            'codigo_grupo'    => $g->codigo_grupo,
            'periodo'         => $g->periodo,
            'nivel'           => $g->nivel,
            'nombre_curso'    => $g->nombre_curso,
            'id_curso'        => $g->id_curso,
            'id_profesor'     => $g->id_profesor,
            'nombre_profesor' => trim($g->prof_nombre . ' ' . $g->prof_apellido),
            'inscritos'       => (int) $g->inscritos,
            'cupo_maximo'     => (int) $g->cupo_maximo,
            'fecha_inicio'    => $g->fecha_inicio,
            'fecha_fin'       => $g->fecha_fin,
            'estatus'         => (bool) $g->estatus,
            'horarios'        => $horarios->values(),
            'nombre' => $g->nombre,
        ]);
    }

    public function editarGrupo(Request $request, $id)
    {
        $grupo = DB::query()->fromSub(LecturaCore::grupos(), 'grupo')->where('id_grupo', (int) $id)->first();
        if (!$grupo) return response()->json(['error' => 'Grupo no encontrado.'], 404);

        $validator = Validator::make($request->all(), [
            'id_curso'     => 'required|integer',
            'id_profesor'  => 'required|integer',
            'codigo_grupo' => 'required|string|max:20',
            'nombre'       => 'nullable|string|max:100',
            'periodo'      => ['nullable', 'string', Rule::in(DB::table('academico.periodo')->pluck('clave'))],
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after:fecha_inicio',
            'cupo_maximo'  => 'required|integer|min:1',
            'horarios'     => 'required|array|min:1',
        ], ['periodo.in' => 'Elige uno de los periodos de la lista.']);
        $validator->after(fn ($v) => self::revisarPeriodo($v, $request));
        if ($validator->fails())
            return response()->json(['errors' => $validator->errors()], 422);

        DB::transaction(fn () => EscrituraCore::grupo((int) $id, [
            'nombre'  => $request->input('nombre', $grupo->nombre),
            'estatus' => $request->input('estatus', $grupo->estatus),
        ] + $request->only([
            'id_curso', 'id_profesor', 'codigo_grupo', 'periodo', 'fecha_inicio', 'fecha_fin', 'cupo_maximo',
        ]), $request->horarios));

        return response()->json(['message' => 'Grupo actualizado correctamente.']);
    }

    public function eliminarGrupo($id)
    {
        $activos = DB::table('academico.inscripcion')
            ->where('id_grupo', (int) $id)
            ->whereIn('estado', ['activa', 'lista_espera'])
            ->count();

        if ($activos > 0)
            return response()->json([
                'error' => "No se puede eliminar: hay {$activos} inscripción(es) activa(s) en este grupo."
            ], 422);

        // Si el grupo tiene historial (inscripciones dadas de baja o facturas) la BD no deja borrarlo;
        // en ese caso no se toca nada, ni siquiera los horarios.
        try {
            DB::transaction(fn () => EscrituraCore::eliminarGrupo((int) $id));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23503') {
                return response()->json([
                    'error' => 'No se puede eliminar porque el grupo tiene historial de inscripciones o pagos. Puedes desactivarlo cambiando su estatus a inactivo.'
                ], 409);
            }
            throw $e;
        }

        return response()->json(['message' => 'Grupo eliminado correctamente.']);
    }

    public function verGrupo($id)
{
    $g = DB::query()->fromSub(LecturaCore::grupos(), 'grupo')
        ->joinSub(LecturaCore::cursos(), 'curso',    'grupo.id_curso',    '=', 'curso.id_curso')
        ->joinSub(LecturaCore::personas('profesor'), 'profesor', 'grupo.id_profesor', '=', 'profesor.id_profesor')
        ->leftJoinSub(LecturaCore::sedes(), 'sede', 'curso.id_sede',     '=', 'sede.id_sede')
        ->selectRaw("
            grupo.id_grupo, grupo.codigo_grupo, grupo.periodo,
            grupo.fecha_inicio, grupo.fecha_fin, grupo.cupo_maximo, grupo.estatus,
            curso.nombre AS nombre_curso, curso.nivel,
            sede.nombre AS nombre_sede,
            profesor.nombre AS prof_nombre,
            profesor.apellido_p AS prof_apellido_p,
            profesor.apellido_m AS prof_apellido_m
        ")
        ->where('grupo.id_grupo', (int) $id)
        ->first();

    if (!$g) abort(404, 'Grupo no encontrado.');

    $g->estatus = filter_var($g->estatus, FILTER_VALIDATE_BOOLEAN);

    $horarios = DB::query()->fromSub(LecturaCore::horarios(), 'horario')
        ->where('id_grupo', (int) $id)
        ->orderBy('dia_semana')
        ->get();
$alumnos = DB::query()->fromSub(LecturaCore::inscripciones(), 'inscripcion')
    ->joinSub(LecturaCore::personas('alumno'), 'alumno', 'inscripcion.id_alumno', '=', 'alumno.id_alumno')
    ->select(
        'alumno.id_alumno',
        'alumno.nombre',
        'alumno.apellido_p',
        'alumno.apellido_m',
        'alumno.email',
        'alumno.telefono',
        'inscripcion.estatus as estatus_inscripcion'
    )
        ->where('inscripcion.id_grupo', (int) $id)
        ->orderBy('alumno.apellido_p')
        ->get()
        ->map(function ($a) {
            $a->estatus_inscripcion = filter_var($a->estatus_inscripcion, FILTER_VALIDATE_BOOLEAN);
            return $a;
        });

    $inscritos = $alumnos->where('estatus_inscripcion', true)->count();

    return view('admin.grupo-detalle', compact('g', 'horarios', 'alumnos', 'inscritos'));
}

    // =========================================================
    // CURSOS
    // =========================================================

    public function buscarCursos(Request $request)
    {
        $q = $request->query('q', '');

        $cursos = DB::query()->fromSub(LecturaCore::cursos(), 'curso')
            ->leftJoinSub(LecturaCore::sedes(), 'sede', 'curso.id_sede', '=', 'sede.id_sede')
            ->selectRaw("
                curso.id_curso,
                curso.nombre,
                curso.nivel,
                curso.duracion_semanas,
                curso.horas_totales,
                curso.costo_base,
                curso.estatus,
                curso.id_sede,
                curso.descripcion,
                curso.requisitos,
                sede.nombre AS nombre_sede
            ")
            ->when($q !== '', function ($query) use ($q) {
                $ql = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($ql) {
                    $sub->whereRaw('LOWER(curso.nombre) LIKE ?', [$ql])
                        ->orWhereRaw('LOWER(curso.nivel::text)  LIKE ?', [$ql])
                        ->orWhereRaw('LOWER(sede.nombre)  LIKE ?', [$ql]);
                });
            })
            ->orderBy('curso.id_curso')
            ->get();

        return response()->json(['data' => $cursos]);
    }

    public function registrarCurso(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre'  => 'required|string|max:50',
            'id_sede' => 'required|integer',
            'nivel'   => ['nullable', 'integer', Rule::in(DB::table('academico.nivel')->pluck('rating_minimo'))],
        ], ['nivel.in' => 'Elige uno de los niveles de la lista.']);
        if ($validator->fails())
            return response()->json(['errors' => $validator->errors()], 422);

        $id = DB::transaction(fn () => EscrituraCore::curso(null, $request->only([
            'nombre', 'id_sede', 'nivel', 'duracion_semanas', 'horas_totales', 'costo_base', 'descripcion', 'requisitos',
        ]) + ['estatus' => $request->input('estatus', true)]));

        return response()->json(['message' => 'Curso creado correctamente.', 'id' => $id]);
    }

    public function obtenerCurso($id)
    {
        $curso = DB::query()->fromSub(LecturaCore::cursos(), 'curso')
            ->leftJoinSub(LecturaCore::sedes(), 'sede', 'curso.id_sede', '=', 'sede.id_sede')
            ->selectRaw("curso.*, sede.nombre AS nombre_sede")
            ->where('curso.id_curso', (int) $id)
            ->first();

        if (!$curso) return response()->json(['error' => 'Curso no encontrado.'], 404);

        return response()->json($curso);
    }

    public function editarCurso(Request $request, $id)
    {
        $curso = DB::query()->fromSub(LecturaCore::cursos(), 'curso')->where('id_curso', (int) $id)->first();
        if (!$curso) return response()->json(['error' => 'Curso no encontrado.'], 404);

        $validator = Validator::make($request->all(), [
            'nombre'  => 'required|string|max:50',
            'id_sede' => 'required|integer',
            'nivel'   => ['nullable', 'integer', Rule::in(DB::table('academico.nivel')->pluck('rating_minimo'))],
        ], ['nivel.in' => 'Elige uno de los niveles de la lista.']);
        if ($validator->fails())
            return response()->json(['errors' => $validator->errors()], 422);

        // Lo que el formulario no mande se queda como está
        DB::transaction(fn () => EscrituraCore::curso((int) $id, [
            'nombre'           => $request->nombre,
            'id_sede'          => $request->id_sede,
            'nivel'            => $request->has('nivel') ? $request->nivel : $curso->nivel,
            'duracion_semanas' => $request->input('duracion_semanas') ?? $curso->duracion_semanas,
            'horas_totales'    => $request->input('horas_totales') ?? $curso->horas_totales,
            'costo_base'       => $request->input('costo_base') ?? $curso->costo_base,
            'estatus'          => $request->input('estatus', $curso->estatus),
            'descripcion'      => $request->input('descripcion', $curso->descripcion),
            'requisitos'       => $request->input('requisitos', $curso->requisitos),
        ]));

        return response()->json(['message' => 'Curso actualizado correctamente.']);
    }

    public function eliminarCurso($id)
    {
        $activos = DB::query()->fromSub(LecturaCore::grupos(), 'grupo')
            ->where('id_curso', (int) $id)
            ->where('estatus', true)
            ->count();

        if ($activos > 0)
            return response()->json([
                'error' => "No se puede eliminar: hay {$activos} grupo(s) activo(s) asociado(s) a este curso."
            ], 422);

        try {
            DB::transaction(fn () => EscrituraCore::eliminarCurso((int) $id));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23503') {
                return response()->json([
                    'error' => 'No se puede eliminar porque el curso tiene grupos registrados (aunque estén inactivos). Puedes desactivarlo cambiando su estatus a inactivo.'
                ], 409);
            }
            throw $e;
        }
        return response()->json(['message' => 'Curso eliminado correctamente.']);
    }

    // =========================================================
    // STATUS DASHBOARD
    // =========================================================

    public function obtenerStatus()
    {
        $totalAlumnos = DB::query()->fromSub(LecturaCore::personas('alumno'), 'alumno')->where('estatus', true)->count();
        $totalExtraescolares = DB::query()->fromSub(LecturaCore::extraescolares(), 'extraescolar')->where('estatus', true)->count();
        $totalProfesores = DB::query()->fromSub(LecturaCore::personas('profesor'), 'profesor')->where('estatus', true)->count();

        $mesActual = DB::query()->fromSub(LecturaCore::personas('alumno'), 'alumno')
            ->whereRaw('EXTRACT(MONTH FROM fecha_registro) = EXTRACT(MONTH FROM CURRENT_DATE)')
            ->whereRaw('EXTRACT(YEAR FROM fecha_registro) = EXTRACT(YEAR FROM CURRENT_DATE)')
            ->count();
            
        $mesAnterior = DB::query()->fromSub(LecturaCore::personas('alumno'), 'alumno')
            ->whereRaw("EXTRACT(MONTH FROM fecha_registro) = EXTRACT(MONTH FROM CURRENT_DATE - INTERVAL '1 month')")
            ->whereRaw("EXTRACT(YEAR FROM fecha_registro) = EXTRACT(YEAR FROM CURRENT_DATE - INTERVAL '1 month')")
            ->count();

        if ($mesAnterior == 0) {
            $crecimiento = "N/A";
        } else {
            $calc = (($mesActual - $mesAnterior) / $mesAnterior) * 100;
            $signo = $calc > 0 ? '+' : '';
            $crecimiento = $signo . round($calc) . "%";
        }

        $actividadAlumnos = DB::query()->fromSub(LecturaCore::personas('alumno'), 'alumno')
            ->selectRaw("'alumno' as tipo, nombre || ' ' || apellido_p as descripcion, fecha_registro as fecha")
            ->orderBy('fecha_registro', 'desc')
            ->limit(5)
            ->get();

        $actividadGrupos = DB::query()->fromSub(LecturaCore::grupos(), 'grupo')
            ->selectRaw("'grupo' as tipo, codigo_grupo as descripcion, fecha_inicio::timestamp as fecha")
            ->orderBy('fecha_inicio', 'desc')
            ->limit(5)
            ->get();

        $actividadMerge = $actividadAlumnos->concat($actividadGrupos)->sortByDesc('fecha')->take(8)->values();
        
        \Carbon\Carbon::setLocale('es');
        $actividadReciente = $actividadMerge->map(function($item) {
            $item->hace = \Carbon\Carbon::parse($item->fecha)->diffForHumans();
            return $item;
        });

        return response()->json([
            'total_alumnos' => $totalAlumnos,
            'total_extraescolares' => $totalExtraescolares,
            'total_profesores' => $totalProfesores,
            'crecimiento' => $crecimiento,
            'actividad_reciente' => $actividadReciente
        ]);
    }
}


