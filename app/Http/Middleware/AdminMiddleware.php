<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !auth()->user()->isModerator()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthorized. Moderator access required.'
                ], 403);
            }
            
            return response()->view('errors.403', [], 403);
        }

        return $next($request);
    }
}
