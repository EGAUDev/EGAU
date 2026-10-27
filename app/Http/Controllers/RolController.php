<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Rol;
use App\Models\Permiso;
use App\Services\LecturaCore;
use App\Services\SincronizadorCore;

class RolController extends Controller
{
    // ID del rol super-administrador (nunca editable ni visible en listado)
    private function getSuperAdminId(): int
    {
        $superAdmin = DB::table('rol')->where('administrativo', true)->orderBy('id_rol')->first();
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
        'nombre'      => 'required|string|max:50|unique:rol,nombre',
        'descripcion' => 'required|string',
        'estatus'     => 'required|boolean',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $esAdmin = filter_var(session('administrativo', false), FILTER_VALIDATE_BOOLEAN);
    $administrativo = $esAdmin ? filter_var($request->input('administrativo', false), FILTER_VALIDATE_BOOLEAN) : false;

    try {
        DB::beginTransaction();
        $permiso = new Permiso();
        $this->llenarPermiso($permiso, $request);
        $permiso->save();

        $rol = new Rol();
        $rol->id_permiso         = $permiso->id_permiso;
        $rol->nombre             = $request->nombre;
        $rol->descripcion        = $request->descripcion;
        $rol->estatus            = filter_var($request->estatus, FILTER_VALIDATE_BOOLEAN);
        $rol->administrativo     = $administrativo;
        $rol->fecha_registro     = now();
        $rol->fecha_modificacion = now();
        $rol->save();

        SincronizadorCore::rol($rol->id_rol);
        DB::commit();

        return response()->json(['message' => 'Rol creado correctamente.', 'id_rol' => $rol->id_rol], 201);
    } catch (\Throwable $e) {
        DB::rollBack();
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

    $rol = Rol::find((int) $id);

    if (!$rol) {
        return response()->json(['error' => 'Rol no encontrado'], 404);
    }

    $validator = Validator::make($request->all(), [
        'nombre'      => 'required|string|max:50|unique:rol,nombre,' . $id . ',id_rol',
        'descripcion' => 'required|string',
        'estatus'     => 'required|boolean',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $esAdmin = filter_var(session('administrativo', false), FILTER_VALIDATE_BOOLEAN);

    try {
        DB::beginTransaction();
        $permiso = Permiso::find($rol->id_permiso);
        if ($permiso) {
            $this->llenarPermiso($permiso, $request);
            $permiso->save();
        }

        $rol->nombre             = $request->nombre;
        $rol->descripcion        = $request->descripcion;
        $rol->estatus            = filter_var($request->estatus, FILTER_VALIDATE_BOOLEAN);
        $rol->fecha_modificacion = now();

        if ($esAdmin) {
            $rol->administrativo = filter_var($request->input('administrativo', false), FILTER_VALIDATE_BOOLEAN);
        }

        $rol->save();

        SincronizadorCore::rol($rol->id_rol);
        DB::commit();

        return response()->json(['message' => 'Rol actualizado correctamente.']);
    } catch (\Throwable $e) {
        DB::rollBack();
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

    $rol = Rol::find((int) $id);

    if (!$rol) {
        return response()->json(['error' => 'Rol no encontrado'], 404);
    }

    $enUso = DB::table('personal')->where('id_rol', (int) $id)->exists();
    if ($enUso) {
        return response()->json(['error' => 'No se puede eliminar el rol porque tiene personal asignado.'], 409);
    }

    try {
        DB::beginTransaction();
        $idPermiso = $rol->id_permiso;
        SincronizadorCore::eliminarRol($rol->id_rol);
        $rol->delete();

        if ($idPermiso) {
            Permiso::find($idPermiso)?->delete();
        }
        DB::commit();

        return response()->json(['message' => 'Rol eliminado correctamente.']);
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
    }
}

    // -------------------------------------------------------
    // Helper: llenar campos de permiso desde request
    // -------------------------------------------------------
    private function llenarPermiso(Permiso $permiso, Request $request): void
    {
        $campos = [
            'alumno_ver', 'alumno_edit',
            'profesor_ver', 'profesor_edit',
            'personal_ver', 'personal_edit',
            'roles_ver', 'roles_edit',
            'sedes_ver', 'sedes_edit',
            'grupos_ver', 'grupos_edit',
            'extracurriculares_ver', 'extracurriculares_edit',
            'estatus_ver',
            'pagos_ver', 'pagos_edit',
            'niveles_ver', 'niveles_edit',
        ];

        foreach ($campos as $campo) {
            $permiso->$campo = (bool) $request->input($campo, false);
        }
    }
}
