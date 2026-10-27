<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class VerificarToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = session('token');

        if (!$token) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No autenticado'], 401);
            }
            return redirect()->route('login');
        }

        $tokenHash = hash('sha256', explode('|', $token)[1] ?? $token);
        $tokenValido = PersonalAccessToken::where('token', $tokenHash)->first();

        if (!$tokenValido || ($tokenValido->expires_at && $tokenValido->expires_at < now())) {
            return $this->rechazar($request, 'Sesión expirada');
        }

        // v2.2: la cuenta debe seguir activa en core.usuario.
        // Una sesión sin id_usuario es de antes del cambio a v2.2: se pide volver a entrar.
        $cuenta = DB::selectOne(
            'SELECT estado FROM core.usuario WHERE id = ?',
            [(int) session('id_usuario')]
        );

        if (!$cuenta || $cuenta->estado !== 'activo') {
            $tokenValido->delete();
            return $this->rechazar($request, 'Cuenta inactiva');
        }

   $response = $next($request);
   $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
   $response->headers->set('Pragma', 'no-cache');
   $response->headers->set('Expires', '0');
   return $response;    
    }

    private function rechazar(Request $request, string $motivo)
    {
        session()->forget(['token', 'id_usuario']);
        if ($request->expectsJson()) {
            return response()->json(['error' => $motivo], 401);
        }
        return redirect()->route('login');
    }
}
