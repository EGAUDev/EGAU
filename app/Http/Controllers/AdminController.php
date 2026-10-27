<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alumno;
use App\Models\Profesor;
use App\Models\Personal;
use App\Services\SincronizadorCore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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

        $rules = [
            'nombre' => 'required|string|max:50',
            'apellido_p' => 'required|string|max:50',
            'email' => "required|email|unique:{$tipo},email|max:100",
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
            $rules['id_sede'] = 'required|integer';
        } elseif ($tipo === 'profesor') {
            $rules['id_sede'] = 'required|integer';
        } elseif ($tipo === 'personal') {
            $rules['id_rol'] = 'required|integer';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (SincronizadorCore::correoOcupado($request->email)) {
            return response()->json(['errors' => ['email' => ['Ese correo ya tiene una cuenta en el sistema.']]], 422);
        }

        // Asignación de propiedades manualmente respetando modelos existentes
        if ($tipo === 'alumno') {
            $usuario = new Alumno();

            if ($request->filled('tu_nombre')) {
                $tutor = new \App\Models\Tutor();
                $tutor->nombre = $request->tu_nombre;
                $tutor->apellido_p = $request->tu_ap_paterno;
                $tutor->apellido_m = $request->tu_ap_materno;
                $tutor->parentesco = $request->tu_parentesco;
                $tutor->telefono = $request->tu_telefono;
                $tutor->email = $request->tu_correo;
                $tutor->save();

                $usuario->id_tutor = $tutor->id_tutor;
            } elseif ($request->has('id_tutor')) {
                $usuario->id_tutor = $request->id_tutor;
            }

            $usuario->id_sede = $request->id_sede;
            $usuario->puntaje = $request->puntos ?? 0;
        } elseif ($tipo === 'profesor') {
            $usuario = new Profesor();
            $usuario->id_sede = $request->id_sede;
        } elseif ($tipo === 'personal') {
            $usuario = new Personal();
            $usuario->id_rol = $request->id_rol;
        }

        $usuario->nombre = $request->nombre;
        $usuario->apellido_p = $request->apellido_p;

        if ($request->filled('apellido_m')) {
            $usuario->apellido_m = $request->apellido_m;
        }

        if ($request->filled('estado_residencia'))
            $usuario->estado_residencia = $request->estado_residencia;
        if ($request->filled('ciudad'))
            $usuario->ciudad = $request->ciudad;
        if ($request->filled('calle'))
            $usuario->calle = substr($request->calle, 0, 255);
        if ($request->filled('codigo_postal'))
            $usuario->codigo_postal = $request->codigo_postal;
        if ($request->filled('genero'))
            $usuario->genero = strtolower($request->genero);
        $usuario->fecha_nacimiento = $request->fecha_nacimiento;
        if ($request->filled('telefono'))
            $usuario->telefono = $request->telefono;
        $usuario->email = $request->email;
        $usuario->contraseña = Hash::make($request->input('contraseña'));
        $usuario->estatus = $request->input('estatus', true);

        DB::transaction(function () use ($usuario, $tipo) {
            $usuario->save();
            SincronizadorCore::persona($tipo, $usuario->getKey(), true);
        });

        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'usuario' => $usuario
        ], 201);
    }

    public function editarUsuario(Request $request, $tipo, $id)
    {
        if (!in_array($tipo, ['alumno', 'profesor', 'personal'])) {
            return response()->json(['error' => 'Tipo de usuario inválido'], 400);
        }

        $usuario = null;
        if ($tipo === 'alumno')
            $usuario = Alumno::find($id);
        elseif ($tipo === 'profesor')
            $usuario = Profesor::find($id);
        elseif ($tipo === 'personal')
            $usuario = Personal::find($id);

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $rules = [
            'nombre' => 'sometimes|required|string|max:50',
            'apellido_p' => 'sometimes|required|string|max:50',
            'email' => "sometimes|required|email|max:100|unique:{$tipo},email,{$id},id_{$tipo}",
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
            $rules['id_sede'] = 'nullable|integer';
            $rules['puntaje'] = 'nullable|integer';
        } elseif ($tipo === 'profesor') {
            $rules['id_sede'] = 'nullable|integer';
            $rules['puntaje'] = 'nullable|integer';
        } elseif ($tipo === 'personal') {
            $rules['id_rol'] = 'nullable|integer';
        }

        // El formulario de alumnos manda el puntaje como "puntos" (igual que en el alta)
        if (in_array($tipo, ['alumno', 'profesor']) && !$request->has('puntaje') && $request->has('puntos')) {
            $request->merge(['puntaje' => $request->input('puntos')]);
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->filled('email') && SincronizadorCore::correoOcupado($request->email, $tipo, (int) $id)) {
            return response()->json(['errors' => ['email' => ['Ese correo ya tiene una cuenta en el sistema.']]], 422);
        }

        $fillableFields = [
            'nombre',
            'apellido_p',
            'apellido_m',
            'estado_residencia',
            'ciudad',
            'calle',
            'codigo_postal',
            'genero',
            'fecha_nacimiento',
            'telefono',
            'email',
            'estatus'
        ];

        if ($tipo === 'alumno')
            $fillableFields[] = 'puntaje';
        if ($tipo === 'profesor')
            $fillableFields[] = 'puntaje';

        foreach ($fillableFields as $field) {
            if ($request->has($field)) {
                // Convertir género a minúscula para el enum de PostgreSQL
                if ($field === 'genero') {
                    $usuario->genero = strtolower($request->$field);
                } elseif ($field === 'calle') {
                    $usuario->calle = substr($request->$field, 0, 255);
                } else {
                    $usuario->$field = $request->$field;
                }
            }
        }

        if ($request->filled('contraseña')) {
            $usuario->contraseña = Hash::make($request->contraseña);
        }

        if ($tipo === 'alumno' && $request->has('id_tutor')) {
            $usuario->id_tutor = $request->id_tutor;
        }
        if (in_array($tipo, ['alumno', 'profesor']) && $request->has('id_sede')) {
            $usuario->id_sede = $request->id_sede;
        }
        if ($tipo === 'personal' && $request->has('id_rol')) {
            $usuario->id_rol = $request->id_rol;
        }

        DB::transaction(function () use ($usuario, $tipo, $request) {
            $usuario->save();
            SincronizadorCore::persona($tipo, $usuario->getKey(), $request->filled('contraseña'));
        });

        return response()->json([
            'message' => ucfirst($tipo) . ' actualizado exitosamente',
            'usuario' => $usuario
        ]);
    }

    public function eliminarUsuario($tipo, $id)
    {
        if (!in_array($tipo, ['alumno', 'profesor', 'personal'])) {
            return response()->json(['error' => 'Tipo de usuario inválido'], 400);
        }

        $usuario = null;
        if ($tipo === 'alumno')
            $usuario = Alumno::find($id);
        elseif ($tipo === 'profesor')
            $usuario = Profesor::find($id);
        elseif ($tipo === 'personal')
            $usuario = Personal::find($id);

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        try {
            DB::transaction(function () use ($usuario, $tipo, $id) {
                $usuario->delete();
                SincronizadorCore::eliminarPersona($tipo, (int) $id);
            });
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

        $usuario = match ($tipo) {
            'alumno' => Alumno::find($id),
            'profesor' => Profesor::find($id),
            'personal' => Personal::find($id),
        };

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
            'alumno' => Alumno::leftJoin('sede', 'alumno.id_sede', '=', 'sede.id_sede')
                ->select('alumno.*', 'sede.nombre as nombre_sede')
                ->orderBy('alumno.id_alumno', 'asc'),
            'profesor' => Profesor::leftJoin('sede', 'profesor.id_sede', '=', 'sede.id_sede')
                ->select('profesor.*', 'sede.nombre as nombre_sede')
                ->orderBy('profesor.id_profesor', 'asc'),
            'personal' => Personal::leftJoin('rol', 'personal.id_rol', '=', 'rol.id_rol')
                ->leftJoin('usuariosede', 'personal.id_personal', '=', 'usuariosede.id_personal')
                ->leftJoin('sede', 'usuariosede.id_sede', '=', 'sede.id_sede')
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
                'info' => $r->toArray(),
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

        $query = \App\Models\Sede::query();

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

        $sede = new \App\Models\Sede();
        $sede->nombre = $request->nombre;
        $sede->estado_residencia = $request->estado_residencia;
        $sede->ciudad = $request->ciudad;
        $sede->codigo_postal = $request->codigo_postal;
        $sede->calle = $request->calle;
        $sede->telefono = $request->telefono;
        $sede->email = $request->email;
        $sede->estatus = true;
        DB::transaction(function () use ($sede) {
            $sede->save();
            SincronizadorCore::sede($sede->id_sede);
        });

        return response()->json(['message' => 'Sede registrada correctamente.', 'id' => $sede->id_sede]);
    }

    public function obtenerSede($id)
    {
        $sede = \App\Models\Sede::find($id);
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
        $sede = \App\Models\Sede::find($id);
        if (!$sede)
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

        $sede->nombre = $request->nombre;
        $sede->estado_residencia = $request->estado_residencia;
        $sede->ciudad = $request->ciudad;
        $sede->codigo_postal = $request->codigo_postal;
        $sede->calle = $request->calle;
        $sede->telefono = $request->telefono;
        $sede->email = $request->email;
        DB::transaction(function () use ($sede) {
            $sede->save();
            SincronizadorCore::sede($sede->id_sede);
        });

        return response()->json(['message' => 'Sede actualizada correctamente.']);
    }

    public function eliminarSede($id)
    {
        $sede = \App\Models\Sede::find($id);
        if (!$sede)
            return response()->json(['error' => 'Sede no encontrada.'], 404);

        try {
            DB::transaction(function () use ($sede, $id) {
                $sede->delete();
                SincronizadorCore::eliminarSede((int) $id);
            });
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

        $grupos = \Illuminate\Support\Facades\DB::table('grupo')
            ->join('curso',    'grupo.id_curso',    '=', 'curso.id_curso')
            ->join('profesor', 'grupo.id_profesor', '=', 'profesor.id_profesor')
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
                (SELECT COUNT(*) FROM inscripcion
                 WHERE inscripcion.id_grupo = grupo.id_grupo
                   AND inscripcion.estatus = true) AS inscritos
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
        $horariosPorGrupo = \Illuminate\Support\Facades\DB::table('horario')
            ->whereIn('id_grupo', $ids)
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

    public function registrarGrupo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_curso'     => 'required|integer',
            'id_profesor'  => 'required|integer',
            'codigo_grupo' => 'required|string|max:20',
            'periodo' => 'nullable|string|max:20',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after:fecha_inicio',
            'cupo_maximo'  => 'required|integer|min:1',
            'horarios'     => 'required|array|min:1',
        ]);
        if ($validator->fails())
            return response()->json(['errors' => $validator->errors()], 422);

        $grupo = new \App\Models\Academico\Grupo();
        $grupo->id_curso     = $request->id_curso;
        $grupo->id_profesor  = $request->id_profesor;
        $grupo->codigo_grupo = $request->codigo_grupo;
        $grupo->nombre = $request->input('nombre', '');
      $grupo->periodo = $request->input('periodo', '');
        $grupo->fecha_inicio = $request->fecha_inicio;
        $grupo->fecha_fin    = $request->fecha_fin;
        $grupo->cupo_maximo  = $request->cupo_maximo;
        $grupo->estatus      = filter_var($request->input('estatus', true), FILTER_VALIDATE_BOOLEAN);
        $grupo->save();

        foreach ($request->horarios as $h) {
            \Illuminate\Support\Facades\DB::table('horario')->insert([
                'id_grupo'    => $grupo->id_grupo,
                'dia_semana'  => (int) $h['dia_semana'],
                'hora_inicio' => $h['hora_inicio'],
                'hora_fin'    => $h['hora_fin'],
                'ubicacion'   => $h['ubicacion'] ?? '',
            ]);
        }

        return response()->json(['message' => 'Grupo creado correctamente.', 'id' => $grupo->id_grupo]);
    }

    public function obtenerGrupo($id)
    {
        $g = \Illuminate\Support\Facades\DB::table('grupo')
            ->join('curso',    'grupo.id_curso',    '=', 'curso.id_curso')
            ->join('profesor', 'grupo.id_profesor', '=', 'profesor.id_profesor')
            ->selectRaw("
                grupo.nombre,
                grupo.id_grupo, grupo.codigo_grupo, grupo.periodo,
                grupo.fecha_inicio, grupo.fecha_fin, grupo.cupo_maximo, grupo.estatus,
                grupo.id_curso, grupo.id_profesor,
                curso.nivel, curso.nombre AS nombre_curso,
                profesor.nombre AS prof_nombre, profesor.apellido_p AS prof_apellido,
                (SELECT COUNT(*) FROM inscripcion
                 WHERE inscripcion.id_grupo = grupo.id_grupo
                   AND inscripcion.estatus = true) AS inscritos
            ")
            ->where('grupo.id_grupo', (int) $id)
            ->first();

        if (!$g) return response()->json(['error' => 'Grupo no encontrado.'], 404);

        $horarios = \Illuminate\Support\Facades\DB::table('horario')
            ->where('id_grupo', (int) $id)
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
        $grupo = \App\Models\Academico\Grupo::find((int) $id);
        if (!$grupo) return response()->json(['error' => 'Grupo no encontrado.'], 404);

        $validator = Validator::make($request->all(), [
            'id_curso'     => 'required|integer',
            'id_profesor'  => 'required|integer',
            'codigo_grupo' => 'required|string|max:20',
            'periodo' => 'nullable|string|max:20',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after:fecha_inicio',
            'cupo_maximo'  => 'required|integer|min:1',
            'horarios'     => 'required|array|min:1',
        ]);
        if ($validator->fails())
            return response()->json(['errors' => $validator->errors()], 422);

        $grupo->id_curso     = $request->id_curso;
        $grupo->id_profesor  = $request->id_profesor;
        $grupo->codigo_grupo = $request->codigo_grupo;
        $grupo->nombre = $request->input('nombre', $grupo->nombre ?? '');
        $grupo->periodo = $request->input('periodo', '');
        $grupo->fecha_inicio = $request->fecha_inicio;
        $grupo->fecha_fin    = $request->fecha_fin;
        $grupo->cupo_maximo  = $request->cupo_maximo;
        $grupo->estatus      = filter_var($request->input('estatus', $grupo->estatus), FILTER_VALIDATE_BOOLEAN);
        $grupo->save();

        \Illuminate\Support\Facades\DB::table('horario')->where('id_grupo', $grupo->id_grupo)->delete();
        foreach ($request->horarios as $h) {
            \Illuminate\Support\Facades\DB::table('horario')->insert([
                'id_grupo'    => $grupo->id_grupo,
                'dia_semana'  => (int) $h['dia_semana'],
                'hora_inicio' => $h['hora_inicio'],
                'hora_fin'    => $h['hora_fin'],
                'ubicacion'   => $h['ubicacion'] ?? '',
            ]);
        }

        return response()->json(['message' => 'Grupo actualizado correctamente.']);
    }

    public function eliminarGrupo($id)
    {
        $activos = \Illuminate\Support\Facades\DB::table('inscripcion')
            ->where('id_grupo', (int) $id)
            ->where('estatus', true)
            ->count();

        if ($activos > 0)
            return response()->json([
                'error' => "No se puede eliminar: hay {$activos} inscripción(es) activa(s) en este grupo."
            ], 422);

        \Illuminate\Support\Facades\DB::table('horario')->where('id_grupo', (int) $id)->delete();
        \App\Models\Academico\Grupo::destroy((int) $id);

        return response()->json(['message' => 'Grupo eliminado correctamente.']);
    }

    public function verGrupo($id)
{
    $g = \Illuminate\Support\Facades\DB::table('grupo')
        ->join('curso',    'grupo.id_curso',    '=', 'curso.id_curso')
        ->join('profesor', 'grupo.id_profesor', '=', 'profesor.id_profesor')
        ->leftJoin('sede', 'curso.id_sede',     '=', 'sede.id_sede')
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

    $horarios = \Illuminate\Support\Facades\DB::table('horario')
        ->where('id_grupo', (int) $id)
        ->orderBy('dia_semana')
        ->get();
$alumnos = \Illuminate\Support\Facades\DB::table('inscripcion')
    ->join('alumno', 'inscripcion.id_alumno', '=', 'alumno.id_alumno')
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

        $cursos = \Illuminate\Support\Facades\DB::table('curso')
            ->leftJoin('sede', 'curso.id_sede', '=', 'sede.id_sede')
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
        ]);
        if ($validator->fails())
            return response()->json(['errors' => $validator->errors()], 422);

        $curso = new \App\Models\Academico\Curso();
        $curso->nombre           = $request->nombre;
        $curso->id_sede          = $request->id_sede;
        $curso->nivel            = $request->input('nivel', '');
        $curso->duracion_semanas = (int) $request->input('duracion_semanas', 0);
        $curso->horas_totales    = (int) $request->input('horas_totales', 0);
        $curso->costo_base       = $request->input('costo_base');
        $curso->estatus          = filter_var($request->input('estatus', true), FILTER_VALIDATE_BOOLEAN);
        $curso->descripcion      = $request->input('descripcion');
        $curso->requisitos       = $request->input('requisitos');
        $curso->save();

        return response()->json(['message' => 'Curso creado correctamente.', 'id' => $curso->id_curso]);
    }

    public function obtenerCurso($id)
    {
        $curso = \Illuminate\Support\Facades\DB::table('curso')
            ->leftJoin('sede', 'curso.id_sede', '=', 'sede.id_sede')
            ->selectRaw("curso.*, sede.nombre AS nombre_sede")
            ->where('curso.id_curso', (int) $id)
            ->first();

        if (!$curso) return response()->json(['error' => 'Curso no encontrado.'], 404);

        return response()->json($curso);
    }

    public function editarCurso(Request $request, $id)
    {
        $curso = \App\Models\Academico\Curso::find((int) $id);
        if (!$curso) return response()->json(['error' => 'Curso no encontrado.'], 404);

        $validator = Validator::make($request->all(), [
            'nombre'  => 'required|string|max:50',
            'id_sede' => 'required|integer',
        ]);
        if ($validator->fails())
            return response()->json(['errors' => $validator->errors()], 422);

        $curso->nombre           = $request->nombre;
        $curso->id_sede          = $request->id_sede;
        $curso->nivel            = $request->input('nivel', $curso->nivel);
        $curso->duracion_semanas = $request->input('duracion_semanas') ?? $curso->duracion_semanas;
        $curso->horas_totales    = $request->input('horas_totales')    ?? $curso->horas_totales;
        $curso->costo_base       = $request->input('costo_base')       ?? $curso->costo_base;
        $curso->estatus          = filter_var($request->input('estatus', $curso->estatus), FILTER_VALIDATE_BOOLEAN);
        $curso->descripcion      = $request->input('descripcion', $curso->descripcion);
        $curso->requisitos       = $request->input('requisitos',  $curso->requisitos);
        $curso->save();

        return response()->json(['message' => 'Curso actualizado correctamente.']);
    }

    public function eliminarCurso($id)
    {
        $activos = \Illuminate\Support\Facades\DB::table('grupo')
            ->where('id_curso', (int) $id)
            ->where('estatus', true)
            ->count();

        if ($activos > 0)
            return response()->json([
                'error' => "No se puede eliminar: hay {$activos} grupo(s) activo(s) asociado(s) a este curso."
            ], 422);

        \App\Models\Academico\Curso::destroy((int) $id);
        return response()->json(['message' => 'Curso eliminado correctamente.']);
    }

    // =========================================================
    // STATUS DASHBOARD
    // =========================================================

    public function obtenerStatus()
    {
        $totalAlumnos = \Illuminate\Support\Facades\DB::table('alumno')->where('estatus', true)->count();
        $totalExtraescolares = \Illuminate\Support\Facades\DB::table('extraescolar')->where('estatus', true)->count();
        $totalProfesores = \Illuminate\Support\Facades\DB::table('profesor')->where('estatus', true)->count();

        $mesActual = \Illuminate\Support\Facades\DB::table('alumno')
            ->whereRaw('EXTRACT(MONTH FROM fecha_registro) = EXTRACT(MONTH FROM CURRENT_DATE)')
            ->whereRaw('EXTRACT(YEAR FROM fecha_registro) = EXTRACT(YEAR FROM CURRENT_DATE)')
            ->count();
            
        $mesAnterior = \Illuminate\Support\Facades\DB::table('alumno')
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

        $actividadAlumnos = \Illuminate\Support\Facades\DB::table('alumno')
            ->selectRaw("'alumno' as tipo, nombre || ' ' || apellido_p as descripcion, fecha_registro as fecha")
            ->orderBy('fecha_registro', 'desc')
            ->limit(5)
            ->get();

        $actividadGrupos = \Illuminate\Support\Facades\DB::table('grupo')
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


