<?php

namespace App\Http\Middleware;

use App\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasRole('admin')) {
            if ($request->wantsJson()) {
                return ApiResponse::forbidden('Admin access required.');
            }

            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
