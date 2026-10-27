<?php
namespace App\Http\Middleware;

use App\Services\AccesoCore;
use Closure;
use Illuminate\Http\Request;

/**
 * Revisa en el servidor que el usuario tenga el permiso de v2.2 que pide la ruta.
 * Antes solo se ocultaban los menús y botones; las rutas respondían a cualquiera con sesión.
 *
 * Uso en rutas:
 *   permiso:grupo.ver                  un permiso
 *   permiso:profesor.ver|grupo.editar  basta con uno de ellos
 *   permiso:{tipo}.ver                 {tipo} sale de la ruta o del campo tipo_usuario (alumno, profesor, personal)
 *   permiso:grupo.editar,global        además pide rol global, igual que los botones de borrar
 *
 * Se lee de core.v_permiso_efectivo en cada petición, así que un cambio de rol aplica al momento.
 */
class TienePermiso
{
    // Los profesores no tienen roles en v2.2; ven lo mismo que ya les mostraba el dashboard
    private const PERMISOS_PROFESOR = ['grupo.ver', 'nivel.ver'];

    private const TIPOS_USUARIO = ['alumno', 'profesor', 'personal'];

    public function handle(Request $request, Closure $next, string $requeridos, ?string $alcance = null)
    {
        $tipo = session('tipo');
        $idUsuario = (int) session('id_usuario');

        if ($tipo === 'alumno' || !$idUsuario) {
            return $this->negar($request);
        }

        if (str_contains($requeridos, '{tipo}')) {
            $tipoUsuario = $request->route('tipo') ?? $request->input('tipo_usuario');
            if (!in_array($tipoUsuario, self::TIPOS_USUARIO, true)) {
                return $this->negar($request);
            }
            $requeridos = str_replace('{tipo}', $tipoUsuario, $requeridos);
        }

        $tiene = $tipo === 'profesor' ? self::PERMISOS_PROFESOR : AccesoCore::permisos($idUsuario);

        if (!array_intersect(explode('|', $requeridos), $tiene)) {
            return $this->negar($request);
        }
        if ($alcance === 'global' && ($tipo === 'profesor' || !AccesoCore::esGlobal($idUsuario))) {
            return $this->negar($request);
        }

        return $next($request);
    }

    private function negar(Request $request)
    {
        if ($request->expectsJson() || $request->is('admin/*', 'api/*')) {
            return response()->json(['error' => 'No tienes permiso para hacer esto.'], 403);
        }
        abort(403);
    }
}
