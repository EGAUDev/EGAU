<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
            session()->forget('token');
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }
            return redirect()->route('login');
        }

   $response = $next($request);
   $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
   $response->headers->set('Pragma', 'no-cache');
   $response->headers->set('Expires', '0');
   return $response;    
    }
}