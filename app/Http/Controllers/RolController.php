<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\EscrituraCore;
use App\Services\LecturaCore;
use Illuminate\Validation\Rule;

class RolController extends Controller
{
    // ID del rol super-administrador (nunca editable ni visible en listado)
    private function getSuperAdminId(): int
    {
        $superAdmin = DB::query()->fromSub(LecturaCore::roles(), 'rol')->where('administrativo', true)->orderBy('id_rol')->first();
        return $superAdmin ? (int) $superAdmin->id_rol : 1;
    }

    // -------------------------------------------------------
    // GET /admin/roles?q=&estatus=
    // -------------------------------------------------------
    public function listar(Request $request)
    {
        $q       = $request->query('q', '');
        $estatus = $request->query('estatus', '');

        $superAdminId = $this->getSuperAdminId();

        $query = DB::query()->fromSub(LecturaCore::roles(), 'rol')
            ->where('rol.id_rol', '!=', $superAdminId)
            ->orderBy('rol.id_rol');

        if ($q !== '') {
            $ql = '%' . strtolower($q) . '%';
            $query->where(function ($sub) use ($ql) {
                $sub->whereRaw('LOWER(rol.nombre) LIKE ?', [$ql])
                    ->orWhereRaw('LOWER(rol.descripcion) LIKE ?', [$ql]);
                if (is_numeric(trim($ql, '%'))) {
                    $sub->orWhere('rol.id_rol', (int) trim($ql, '%'));
                }
            });
        }

        if ($estatus !== '') {
            $esActivo = in_array($estatus, ['activo', 'true', '1'], true);
            $query->where('rol.estatus', $esActivo);
        }

        $roles = $query->get();

        return response()->json(['total' => $roles->count(), 'data' => $roles]);
    }

    // -------------------------------------------------------
    // GET /admin/roles/{id}
    // -------------------------------------------------------
    public function obtener($id)
    {
        $superAdminId = $this->getSuperAdminId();

        if ((int) $id === $superAdminId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $rol = DB::query()->fromSub(LecturaCore::roles(), 'rol')
            ->where('rol.id_rol', (int) $id)
            ->first();

        if (!$rol) {
            return response()->json(['error' => 'Rol no encontrado'], 404);
        }

        return response()->json($rol);
    }

    // -------------------------------------------------------
    // POST /admin/roles
    // -------------------------------------------------------
public function crear(Request $request)
{
    $validator = Validator::make($request->all(), [
        'nombre'      => ['required', 'string', 'max:50', Rule::notIn(DB::table('core.rol')->pluck('nombre'))],
        'descripcion' => 'required|string',
        'estatus'     => 'required|boolean',
    ]);

    $validator->setCustomMessages(['nombre.not_in' => 'Ya existe un rol con ese nombre.']);
    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $esAdmin = filter_var(session('administrativo', false), FILTER_VALIDATE_BOOLEAN);
    $administrativo = $esAdmin ? filter_var($request->input('administrativo', false), FILTER_VALIDATE_BOOLEAN) : false;

    try {
        $idRol = DB::transaction(fn () => EscrituraCore::rol(null, [
            'nombre'         => $request->nombre,
            'descripcion'    => $request->descripcion,
            'estatus'        => $request->estatus,
            'administrativo' => $administrativo,
        ], $this->banderas($request)));

        return response()->json(['message' => 'Rol creado correctamente.', 'id_rol' => $idRol], 201);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Error al crear el rol: ' . $e->getMessage()], 500);
    }
}

    // -------------------------------------------------------
    // PUT /admin/roles/{id}
    // -------------------------------------------------------
 

 public function editar(Request $request, $id)
{
    $superAdminId = $this->getSuperAdminId();

    if ((int) $id === $superAdminId) {
        return response()->json(['error' => 'El rol de administrador central no puede modificarse.'], 403);
    }

    $rol = DB::query()->fromSub(LecturaCore::roles(), 'rol')->where('id_rol', (int) $id)->first();

    if (!$rol) {
        return response()->json(['error' => 'Rol no encontrado'], 404);
    }

    $validator = Validator::make($request->all(), [
        'nombre'      => ['required', 'string', 'max:50', Rule::notIn(DB::table('core.rol')->where('id', '!=', (int) $id)->pluck('nombre'))],
        'descripcion' => 'required|string',
        'estatus'     => 'required|boolean',
    ]);

    $validator->setCustomMessages(['nombre.not_in' => 'Ya existe un rol con ese nombre.']);
    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $esAdmin = filter_var(session('administrativo', false), FILTER_VALIDATE_BOOLEAN);

    $datos = [
        'nombre'      => $request->nombre,
        'descripcion' => $request->descripcion,
        'estatus'     => $request->estatus,
    ];
    if ($esAdmin) {
        $datos['administrativo'] = filter_var($request->input('administrativo', false), FILTER_VALIDATE_BOOLEAN);
    }

    try {
        DB::transaction(fn () => EscrituraCore::rol((int) $id, $datos, $this->banderas($request)));

        return response()->json(['message' => 'Rol actualizado correctamente.']);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Error al actualizar el rol: ' . $e->getMessage()], 500);
    }
}
   // -------------------------------------------------------
    // DELETE /admin/roles/{id}
    // -------------------------------------------------------
    public function eliminar($id)
{
    $superAdminId = $this->getSuperAdminId();

    if ((int) $id === $superAdminId) {
        return response()->json(['error' => 'El rol de administrador central no puede eliminarse.'], 403);
    }

    $rol = DB::query()->fromSub(LecturaCore::roles(), 'rol')->where('id_rol', (int) $id)->first();

    if (!$rol) {
        return response()->json(['error' => 'Rol no encontrado'], 404);
    }

    $enUso = DB::table('core.usuario_rol')->where('id_rol', (int) $id)->exists();
    if ($enUso) {
        return response()->json(['error' => 'No se puede eliminar el rol porque tiene personal asignado.'], 409);
    }

    try {
        DB::transaction(fn () => EscrituraCore::eliminarRol((int) $id));

        return response()->json(['message' => 'Rol eliminado correctamente.']);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
    }
}

    // -------------------------------------------------------
    // Helper: casillas de permisos que mandó el formulario
    // -------------------------------------------------------
    private function banderas(Request $request): array
    {
        $campos = array_keys(LecturaCore::BANDERAS);

        $banderas = [];
        foreach ($campos as $campo) {
            $banderas[$campo] = filter_var($request->input($campo, false), FILTER_VALIDATE_BOOLEAN);
        }
        return $banderas;
    }
}
