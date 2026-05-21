<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditMiddleware
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && $response->isSuccessful()) {
            $routeName = $request->route()?->getName();
            $uriPrefix = explode('/', $request->path())[1] ?? null;

            $this->auditService->log(
                action: 'api_request',
                entity: $uriPrefix ?? 'api',
                entityId: null,
                user: $request->user(),
                details: [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status_code' => $response->getStatusCode(),
                    'route_name' => $routeName,
                ],
                request: $request,
            );
        }

        return $response;
    }
}
