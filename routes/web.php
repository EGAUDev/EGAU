<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\ExtraescolarController;
use App\Http\Controllers\NivelesController;
use App\Http\Controllers\PersonalProfileController;
use App\Http\Controllers\AlumnoProfileController;
use App\Http\Middleware\VerificarToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Models\Alumno;
use App\Models\Profesor;
use App\Models\Personal;

Route::get('/', function () {
    return view('website.landing');
})->name('landing');

Route::get('/cursos', function () {
    return view('website.cursos');
})->name('cursos');

Route::get('/sedes', function () {
    return view('website.sedes');
})->name('sedes');

Route::get('/testimonios', function () {
    return view('website.testimonios');
})->name('testimonios');

Route::get('/blog', function () {
    return view('website.blog');
})->name('blog');

Route::get('/contacto', function () {
    return view('website.contacto');
})->name('contacto');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

// ── Logout — fuera del grupo middleware ───────────────────────────────
Route::post('/logout', function () {
    $token = session('token');
    if ($token) {
        $tokenHash = hash('sha256', explode('|', $token)[1] ?? $token);
        \Laravel\Sanctum\PersonalAccessToken::where('token', $tokenHash)->delete();
    }
    session()->flush();
    session()->regenerate();
    return redirect()->route('login');
})->name('logout');

Route::post('/guardar-token', function (Request $request) {
    session([
        'token'         => $request->input('token'),
        'tipo'          => $request->input('tipo'),
        'id_sede'       => $request->input('id_sede'),
        'administrativo'=> $request->input('administrativo'),
        'permisos'      => $request->input('permisos'),
        'id_profesor'   => $request->input('id_profesor'),
    ]);
    return response()->json(['ok' => true]);
})->name('guardar.token');

Route::middleware(VerificarToken::class)->group(function () {

    Route::get('/dashboardAdmin', function () {
        // 🔒 Bloquear alumnos
        if (session('tipo') === 'alumno') {
            return redirect()->route('dashboard.alumno');
        }

        $alumnos = Alumno::leftJoin('sede', 'alumno.id_sede', '=', 'sede.id_sede')
            ->select('alumno.*', 'sede.nombre as nombre_sede')
            ->orderBy('alumno.id_alumno', 'asc')
            ->get();
        $profesores = Profesor::leftJoin('sede', 'profesor.id_sede', '=', 'sede.id_sede')
            ->select('profesor.*', 'sede.nombre as nombre_sede')
            ->orderBy('profesor.id_profesor', 'asc')
            ->get();
        $personal = Personal::leftJoin('rol', 'personal.id_rol', '=', 'rol.id_rol')
            ->select('personal.*', 'rol.nombre as nombre_rol')
            ->orderBy('personal.id_personal', 'asc')
            ->get();
        $sedes = DB::table('sede')->orderBy('id_sede')->get(['id_sede', 'nombre']);
        $roles = DB::table('rol')->get(['id_rol', 'nombre']);
        $token = session('token');
        $permisosData = session('permisos');
        $permisos = $permisosData ? (object) array_map(fn($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN), $permisosData) : (object)[];
        $administrativo = filter_var(session('administrativo', false), FILTER_VALIDATE_BOOLEAN);
        $tipo = session('tipo', 'personal');
        $idProfesor = session('id_profesor');
        return view('dashboardAdmin', compact('alumnos', 'profesores', 'personal', 'sedes', 'roles', 'permisos', 'administrativo', 'tipo', 'idProfesor'));
    })->name('dashboard.admin');

    Route::get('/dashboardAlumno', function () {
        // 🔒 Bloquear personal y profesores
        if (session('tipo') !== 'alumno') {
            return redirect()->route('dashboard.admin');
        }
        return view('dashboardAlumno');
    })->name('dashboard.alumno');

    // ── Sedes — ANTES de las rutas genéricas ──────────────────────────
    Route::get('/admin/buscar/sedes',          [AdminController::class, 'buscarSedes']);
    Route::post('/admin/registrar/sede',       [AdminController::class, 'registrarSede']);
    Route::get('/admin/obtener/sede/{id}',     [AdminController::class, 'obtenerSede']);
    Route::put('/admin/editar/sede/{id}',      [AdminController::class, 'editarSede']);
    Route::delete('/admin/eliminar/sede/{id}', [AdminController::class, 'eliminarSede']);

    // ── Sedes REST (para dashboardAdminSede.js) ───────────────────────
    Route::get('/admin/sedes/{id}',    [AdminController::class, 'obtenerSede']);
    Route::post('/admin/sedes',        [AdminController::class, 'registrarSede']);
    Route::put('/admin/sedes/{id}',    [AdminController::class, 'editarSede']);
    Route::delete('/admin/sedes/{id}', [AdminController::class, 'eliminarSede']);

    // ── Facturas ──────────────────────────────────────────────────────
    Route::get('/admin/facturas',                [FacturaController::class, 'listar']);
    Route::get('/admin/facturas/{id}',           [FacturaController::class, 'obtener']);
    Route::put('/admin/facturas/{id}/vigencia',  [FacturaController::class, 'actualizarVigencia']);

    // ── Roles y Permisos ──────────────────────────────────────────────
    Route::get('/admin/roles',           [RolController::class, 'listar']);
    Route::get('/admin/roles/{id}',      [RolController::class, 'obtener']);
    Route::post('/admin/roles',          [RolController::class, 'crear']);
    Route::put('/admin/roles/{id}',      [RolController::class, 'editar']);
    Route::delete('/admin/roles/{id}',   [RolController::class, 'eliminar']);

    // ── Extraescolares ────────────────────────────────────────────────
    Route::get('/admin/extraescolares',                      [ExtraescolarController::class, 'listar']);
    Route::get('/admin/extraescolares/{id}',                 [ExtraescolarController::class, 'obtener']);
    Route::post('/admin/extraescolares',                     [ExtraescolarController::class, 'crear']);
    Route::put('/admin/extraescolares/baja/{idInscripcion}', [ExtraescolarController::class, 'darDeBajaAlumno']);
    Route::put('/admin/extraescolares/{id}',                 [ExtraescolarController::class, 'editar']);
    Route::delete('/admin/extraescolares/{id}',              [ExtraescolarController::class, 'eliminar']);

    // ── Niveles ───────────────────────────────────────────────────────
    Route::get('/admin/niveles/alumnos',              [NivelesController::class, 'listar']);
    Route::get('/admin/niveles/grupos',               [NivelesController::class, 'grupos']);
    Route::put('/admin/niveles/alumnos/{id}/puntaje', [NivelesController::class, 'editarPuntaje']);

    // ── Grupos ────────────────────────────────────────────────────────
    Route::get('/admin/grupos/buscar',         [AdminController::class, 'buscarGrupos']);
    Route::post('/admin/grupos/registrar',     [AdminController::class, 'registrarGrupo']);
    Route::get('/admin/grupos/{id}/ver',       [AdminController::class, 'verGrupo']);
    Route::get('/admin/grupos/{id}',           [AdminController::class, 'obtenerGrupo']);
    Route::put('/admin/grupos/{id}',           [AdminController::class, 'editarGrupo']);
    Route::delete('/admin/grupos/{id}',        [AdminController::class, 'eliminarGrupo']);

    // ── Cursos ────────────────────────────────────────────────────────
    Route::get('/admin/cursos/buscar',         [AdminController::class, 'buscarCursos']);
    Route::post('/admin/cursos/registrar',     [AdminController::class, 'registrarCurso']);
    Route::get('/admin/cursos/{id}',           [AdminController::class, 'obtenerCurso']);
    Route::put('/admin/cursos/{id}',           [AdminController::class, 'editarCurso']);
    Route::delete('/admin/cursos/{id}',        [AdminController::class, 'eliminarCurso']);

    // ── Dashboard Status ──────────────────────────────────────────────
    Route::get('/admin/status/datos', [AdminController::class, 'obtenerStatus']);

    // ── Rutas genéricas — DESPUÉS de las específicas ──────────────────
    Route::get('/admin/buscar/{tipo}',           [AdminController::class, 'buscarUsuarios']);
    Route::get('/admin/obtener/{tipo}/{id}',     [AdminController::class, 'obtenerUsuario']);
    Route::post('/admin/registrar',              [AdminController::class, 'registrarUsuario']);
    Route::put('/admin/editar/{tipo}/{id}',      [AdminController::class, 'editarUsuario']);
    Route::delete('/admin/eliminar/{tipo}/{id}', [AdminController::class, 'eliminarUsuario']);

    // ── Perfil personal ───────────────────────────────────────────────
    Route::get('/personal/perfil',          [PersonalProfileController::class, 'show']);
    Route::put('/personal/perfil',          [PersonalProfileController::class, 'update']);
    Route::put('/personal/perfil/password', [PersonalProfileController::class, 'updatePassword']);

    Route::get('/alumno/perfil',            [AlumnoProfileController::class, 'show']);
    Route::put('/alumno/perfil',            [AlumnoProfileController::class, 'update']);
    Route::put('/alumno/perfil/password',   [AlumnoProfileController::class, 'updatePassword']);

    Route::get('/api/alumno/grupos',                              [App\Http\Controllers\AlumnoGruposController::class, 'index']);
    Route::post('/api/alumno/grupos/{id}/{accion}',               [App\Http\Controllers\AlumnoGruposController::class, 'accion']);

    Route::get('/api/alumno/pagos',                               [App\Http\Controllers\AlumnoPagosController::class, 'index']);

    Route::get('/profesor/perfil', function () {
        $idProfesor = session('id_profesor');
        if (!$idProfesor) return response()->json(['error' => 'No autorizado'], 401);
        $profesor = DB::table('profesor')->where('id_profesor', $idProfesor)->first();
        if (!$profesor) return response()->json(['error' => 'No encontrado'], 404);
        return response()->json($profesor);
    });

    Route::put('/profesor/perfil', function (Request $request) {
        $idProfesor = session('id_profesor');
        if (!$idProfesor) return response()->json(['error' => 'No autorizado'], 401);
        DB::table('profesor')->where('id_profesor', $idProfesor)->update([
            'nombre'            => $request->nombre,
            'apellido_p'        => $request->apellido_p,
            'apellido_m'        => $request->apellido_m,
            'telefono'          => $request->telefono,
            'email'             => $request->email,
            'fecha_nacimiento'  => $request->fecha_nacimiento,
            'genero'            => $request->genero,
            'estado_residencia' => $request->estado_residencia,
            'ciudad'            => $request->ciudad,
            'calle'             => $request->calle,
            'codigo_postal'     => $request->codigo_postal,
            'fecha_modificacion'=> now(),
        ]);
        return response()->json(['message' => 'Perfil actualizado correctamente.']);
    });

    Route::put('/profesor/perfil/password', function (Request $request) {
        $idProfesor = session('id_profesor');
        if (!$idProfesor) return response()->json(['error' => 'No autorizado'], 401);
        $nueva = $request->contrasena_nueva;
        if (!$nueva || strlen($nueva) < 6)
            return response()->json(['error' => 'Mínimo 6 caracteres.'], 422);
        DB::table('profesor')->where('id_profesor', $idProfesor)
            ->update(['contraseña' => \Illuminate\Support\Facades\Hash::make($nueva)]);
        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    });

});