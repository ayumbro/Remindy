<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('app.enable_registration')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Registration is currently disabled.'], 403);
            }
            
            return redirect()->route('login')->with([
                'registrationDisabled' => true,
                'message' => 'Registration is currently disabled. Please contact the administrator.'
            ]);
        }

        return $next($request);
    }
}