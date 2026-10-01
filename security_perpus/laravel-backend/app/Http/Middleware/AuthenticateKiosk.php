<?php

namespace App\Http\Middleware;

use App\Models\Computer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setiap PC lab punya token sendiri (header X-Kiosk-Token).
 * Yang disimpan di database hanya hash SHA-256 nya.
 */
class AuthenticateKiosk
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Kiosk-Token');
        if (! $token) {
            return response()->json(['message' => 'Token kiosk tidak ada.'], 401);
        }

        $computer = Computer::where('api_token', hash('sha256', $token))->first();
        if (! $computer) {
            return response()->json(['message' => 'Token kiosk tidak valid.'], 401);
        }

        $request->attributes->set('computer', $computer);

        return $next($request);
    }
}
