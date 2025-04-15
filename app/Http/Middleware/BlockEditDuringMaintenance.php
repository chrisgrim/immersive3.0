<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockEditDuringMaintenance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if we're in maintenance mode
        if (app()->isDownForMaintenance()) {
            $path = $request->path();
            
            // More precise pattern matching for edit, new and create routes
            // Using regex to match "/edit" at the end of a URL segment or as a complete segment
            if (preg_match('/(\/edit$|\/edit\/|\/(new|create)$|\/(new|create)\/)/', $path)) {
                // Return the 503 view for maintenance mode
                return response()->view('errors.503', [], 503);
            }
        }

        return $next($request);
    }
} 