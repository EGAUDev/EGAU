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
use App\Services\AccesoCore;
use App\Services\LecturaCore;
use App\Services\EscrituraCore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\Core\Usuario;

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
    // Permisos, sede y alcance se calculan aquí desde v2.2 (core.*);
    // ya no se confía en lo que manda el navegador.
    $token = (string) $request->input('token');
    $acceso = PersonalAccessToken::findToken($token);

    if (!$acceso || ($acceso->expires_at && $acceso->expires_at->isPast()) || !($acceso->tokenable instanceof Usuario)) {
        return response()->json(['ok' => false, 'message' => 'Token inválido'], 401);
    }

    $cuenta = AccesoCore::desdeToken($acceso->tokenable, $acceso->name);
    if (!$cuenta || $cuenta->estado !== 'activo' || $cuenta->estado_perfil !== 'activo') {
        return response()->json(['ok' => false, 'message' => 'Cuenta inactiva'], 401);
    }

    $contexto = AccesoCore::contexto($cuenta->id_usuario, $cuenta->tipo, $cuenta->id_sede);

    session()->regenerate();
    session([
        'token'         => $token,
        'tipo'          => $cuenta->tipo,
        'id_usuario'    => $cuenta->id_usuario,
        'id_persona'    => $cuenta->id_persona,
        'id_sede'       => $contexto['id_sede'],
        'administrativo'=> $contexto['administrativo'],
        'permisos'      => $contexto['permisos'],
        'id_profesor'   => $cuenta->tipo === 'profesor' ? $cuenta->id_persona : null,
    ]);
    return response()->json(['ok' => true]);
})->name('guardar.token');

Route::middleware(VerificarToken::class)->group(function () {

    Route::get('/dashboardAdmin', function () {
        // 🔒 Bloquear alumnos
        if (session('tipo') === 'alumno') {
            return redirect()->route('dashboard.alumno');
        }

        $alumnos = DB::query()->fromSub(LecturaCore::personas('alumno'), 'alumno')
            ->leftJoinSub(LecturaCore::sedes(), 'sede', 'alumno.id_sede', '=', 'sede.id_sede')
            ->select('alumno.*', 'sede.nombre as nombre_sede')
            ->orderBy('alumno.id_alumno', 'asc')
            ->get();
        $profesores = DB::query()->fromSub(LecturaCore::personas('profesor'), 'profesor')
            ->leftJoinSub(LecturaCore::sedes(), 'sede', 'profesor.id_sede', '=', 'sede.id_sede')
            ->select('profesor.*', 'sede.nombre as nombre_sede')
            ->orderBy('profesor.id_profesor', 'asc')
            ->get();
        $personal = DB::query()->fromSub(LecturaCore::personas('personal'), 'personal')
            ->leftJoinSub(LecturaCore::roles(), 'rol', 'personal.id_rol', '=', 'rol.id_rol')
            ->select('personal.*', 'rol.nombre as nombre_rol')
            ->orderBy('personal.id_personal', 'asc')
            ->get();
        $sedes = DB::query()->fromSub(LecturaCore::sedes(), 'sede')->orderBy('id_sede')->get(['id_sede', 'nombre']);
        $roles = DB::query()->fromSub(LecturaCore::roles(), 'rol')->orderBy('id_rol')->get(['id_rol', 'nombre']);
        $periodos = DB::table('academico.periodo')
            ->selectRaw('clave, nombre, lower(vigencia) AS desde, upper(vigencia) AS hasta')
            ->orderByRaw('lower(vigencia)')->get();
        $token = session('token');
        $permisosData = session('permisos');
        $permisos = $permisosData ? (object) array_map(fn($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN), $permisosData) : (object)[];
        $administrativo = filter_var(session('administrativo', false), FILTER_VALIDATE_BOOLEAN);
        $tipo = session('tipo', 'personal');
        $idProfesor = session('id_profesor');
        return view('dashboardAdmin', compact('alumnos', 'profesores', 'personal', 'sedes', 'roles', 'periodos', 'permisos', 'administrativo', 'tipo', 'idProfesor'));
    })->name('dashboard.admin');

    Route::get('/dashboardAlumno', function () {
        // 🔒 Bloquear personal y profesores
        if (session('tipo') !== 'alumno') {
            return redirect()->route('dashboard.admin');
        }
        return view('dashboardAlumno');
    })->name('dashboard.alumno');

    // ── Sedes — ANTES de las rutas genéricas ──────────────────────────
    Route::get('/admin/buscar/sedes',          [AdminController::class, 'buscarSedes'])->middleware('permiso:sede.ver|grupo.editar');
    Route::post('/admin/registrar/sede',       [AdminController::class, 'registrarSede'])->middleware('permiso:sede.editar');
    Route::get('/admin/obtener/sede/{id}',     [AdminController::class, 'obtenerSede'])->middleware('permiso:sede.ver');
    Route::put('/admin/editar/sede/{id}',      [AdminController::class, 'editarSede'])->middleware('permiso:sede.editar');
    Route::delete('/admin/eliminar/sede/{id}', [AdminController::class, 'eliminarSede'])->middleware('permiso:sede.editar,global');

    // ── Sedes REST (para dashboardAdminSede.js) ───────────────────────
    Route::get('/admin/sedes/{id}',    [AdminController::class, 'obtenerSede'])->middleware('permiso:sede.ver');
    Route::post('/admin/sedes',        [AdminController::class, 'registrarSede'])->middleware('permiso:sede.editar');
    Route::put('/admin/sedes/{id}',    [AdminController::class, 'editarSede'])->middleware('permiso:sede.editar');
    Route::delete('/admin/sedes/{id}', [AdminController::class, 'eliminarSede'])->middleware('permiso:sede.editar,global');

    // ── Facturas ──────────────────────────────────────────────────────
    Route::get('/admin/facturas',                [FacturaController::class, 'listar'])->middleware('permiso:cargo.ver');
    Route::get('/admin/facturas/{id}',           [FacturaController::class, 'obtener'])->middleware('permiso:cargo.ver');
    Route::put('/admin/facturas/{id}/vigencia',  [FacturaController::class, 'actualizarVigencia'])->middleware('permiso:cargo.editar');

    // ── Roles y Permisos ──────────────────────────────────────────────
    Route::get('/admin/roles',           [RolController::class, 'listar'])->middleware('permiso:rol.ver');
    Route::get('/admin/roles/{id}',      [RolController::class, 'obtener'])->middleware('permiso:rol.ver');
    Route::post('/admin/roles',          [RolController::class, 'crear'])->middleware('permiso:rol.editar');
    Route::put('/admin/roles/{id}',      [RolController::class, 'editar'])->middleware('permiso:rol.editar');
    Route::delete('/admin/roles/{id}',   [RolController::class, 'eliminar'])->middleware('permiso:rol.editar,global');

    // ── Extraescolares ────────────────────────────────────────────────
    Route::get('/admin/extraescolares',                      [ExtraescolarController::class, 'listar'])->middleware('permiso:extraescolar.ver');
    Route::get('/admin/extraescolares/{id}',                 [ExtraescolarController::class, 'obtener'])->middleware('permiso:extraescolar.ver');
    Route::post('/admin/extraescolares',                     [ExtraescolarController::class, 'crear'])->middleware('permiso:extraescolar.editar');
    Route::put('/admin/extraescolares/baja/{idInscripcion}', [ExtraescolarController::class, 'darDeBajaAlumno'])->middleware('permiso:extraescolar.editar');
    Route::put('/admin/extraescolares/{id}',                 [ExtraescolarController::class, 'editar'])->middleware('permiso:extraescolar.editar');
    Route::delete('/admin/extraescolares/{id}',              [ExtraescolarController::class, 'eliminar'])->middleware('permiso:extraescolar.editar,global');

    // ── Niveles ───────────────────────────────────────────────────────
    Route::get('/admin/niveles/alumnos',              [NivelesController::class, 'listar'])->middleware('permiso:nivel.ver');
    Route::get('/admin/niveles/grupos',               [NivelesController::class, 'grupos'])->middleware('permiso:nivel.ver');
    Route::put('/admin/niveles/alumnos/{id}/puntaje', [NivelesController::class, 'editarPuntaje'])->middleware('permiso:nivel.editar');

    // ── Grupos ────────────────────────────────────────────────────────
    Route::get('/admin/grupos/buscar',         [AdminController::class, 'buscarGrupos'])->middleware('permiso:grupo.ver');
    Route::post('/admin/grupos/registrar',     [AdminController::class, 'registrarGrupo'])->middleware('permiso:grupo.editar');
    Route::get('/admin/grupos/{id}/ver',       [AdminController::class, 'verGrupo'])->middleware('permiso:grupo.ver');
    Route::get('/admin/grupos/{id}',           [AdminController::class, 'obtenerGrupo'])->middleware('permiso:grupo.ver');
    Route::put('/admin/grupos/{id}',           [AdminController::class, 'editarGrupo'])->middleware('permiso:grupo.editar');
    Route::delete('/admin/grupos/{id}',        [AdminController::class, 'eliminarGrupo'])->middleware('permiso:grupo.editar,global');

    // ── Cursos ────────────────────────────────────────────────────────
    Route::get('/admin/cursos/buscar',         [AdminController::class, 'buscarCursos'])->middleware('permiso:grupo.ver');
    Route::post('/admin/cursos/registrar',     [AdminController::class, 'registrarCurso'])->middleware('permiso:grupo.editar');
    Route::get('/admin/cursos/{id}',           [AdminController::class, 'obtenerCurso'])->middleware('permiso:grupo.ver');
    Route::put('/admin/cursos/{id}',           [AdminController::class, 'editarCurso'])->middleware('permiso:grupo.editar');
    Route::delete('/admin/cursos/{id}',        [AdminController::class, 'eliminarCurso'])->middleware('permiso:grupo.editar,global');

    // ── Dashboard Status ──────────────────────────────────────────────
    Route::get('/admin/status/datos', [AdminController::class, 'obtenerStatus'])->middleware('permiso:estatus.ver');

    // ── Rutas genéricas — DESPUÉS de las específicas ──────────────────
    // La pantalla de grupos lista profesores para asignarlos
    Route::get('/admin/buscar/profesor',         [AdminController::class, 'buscarUsuarios'])->defaults('tipo', 'profesor')->middleware('permiso:profesor.ver|grupo.editar');
    Route::get('/admin/buscar/{tipo}',           [AdminController::class, 'buscarUsuarios'])->middleware('permiso:{tipo}.ver');
    Route::get('/admin/obtener/{tipo}/{id}',     [AdminController::class, 'obtenerUsuario'])->middleware('permiso:{tipo}.ver');
    Route::post('/admin/registrar',              [AdminController::class, 'registrarUsuario'])->middleware('permiso:{tipo}.editar');
    Route::put('/admin/editar/{tipo}/{id}',      [AdminController::class, 'editarUsuario'])->middleware('permiso:{tipo}.editar');
    Route::delete('/admin/eliminar/{tipo}/{id}', [AdminController::class, 'eliminarUsuario'])->middleware('permiso:{tipo}.editar,global');

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
        $profesor = DB::query()->fromSub(LecturaCore::personas('profesor'), 'profesor')->where('id_profesor', $idProfesor)->first();
        if (!$profesor) return response()->json(['error' => 'No encontrado'], 404);
        return response()->json($profesor);
    });

    Route::put('/profesor/perfil', function (Request $request) {
        $idProfesor = session('id_profesor');
        if (!$idProfesor) return response()->json(['error' => 'No autorizado'], 401);
        if ($request->filled('email') && EscrituraCore::correoOcupado($request->email, (int) $idProfesor)) {
            return response()->json(['errors' => ['email' => ['Ese correo ya tiene una cuenta en el sistema.']]], 422);
        }
        DB::transaction(fn () => EscrituraCore::persona('profesor', (int) $idProfesor, $request->only([
            'nombre', 'apellido_p', 'apellido_m', 'telefono', 'email', 'fecha_nacimiento', 'genero',
            'estado_residencia', 'ciudad', 'calle', 'codigo_postal',
        ])));
        return response()->json(['message' => 'Perfil actualizado correctamente.']);
    });

    Route::put('/profesor/perfil/password', function (Request $request) {
        $idProfesor = session('id_profesor');
        if (!$idProfesor) return response()->json(['error' => 'No autorizado'], 401);
        $nueva = $request->contrasena_nueva;
        if (!$nueva || strlen($nueva) < 6)
            return response()->json(['error' => 'Mínimo 6 caracteres.'], 422);
        EscrituraCore::password((int) $idProfesor, $nueva);
        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    });

});