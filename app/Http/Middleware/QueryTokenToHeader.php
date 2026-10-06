<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QueryTokenToHeader
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('token') && ! $request->bearerToken()) {
            $request->headers->set('Authorization', 'Bearer ' . $request->query('token'));
        }

        return $next($request);
    }
}
