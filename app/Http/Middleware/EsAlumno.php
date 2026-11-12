<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Solo deja pasar a un alumno con sesión (las rutas de la biblioteca del alumno). */
class EsAlumno
{
    public function handle(Request $request, Closure $next)
    {
        if (session('tipo') !== 'alumno' || !session('id_persona')) {
            return response()->json(['error' => 'Solo para alumnos.'], 403);
        }
        return $next($request);
    }
}
