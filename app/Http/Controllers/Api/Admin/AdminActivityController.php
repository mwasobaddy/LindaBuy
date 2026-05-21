<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\Api\SuccessResponse;
use App\Services\AuditService;
use Illuminate\Http\Request;

class AdminActivityController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(Request $request)
    {
        if (! $request->user()->hasPermissionTo('view-activity-logs')) {
            abort(403);
        }

        $validated = $request->validate([
            'action' => 'nullable|string|max:100',
            'entity' => 'nullable|string|max:50',
            'user_id' => 'nullable|integer|exists:users,id',
            'entity_id' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = array_filter([
            'action' => $validated['action'] ?? null,
            'entity' => $validated['entity'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'entity_id' => $validated['entity_id'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ], fn ($v) => $v !== null);

        $perPage = $validated['per_page'] ?? 50;

        $logs = $this->auditService->query($filters, $perPage);

        return app(SuccessResponse::class, ['data' => $logs]);
    }

    public function summary(Request $request)
    {
        if (! $request->user()->hasPermissionTo('view-activity-logs')) {
            abort(403);
        }

        $validated = $request->validate([
            'entity' => 'nullable|string|max:50',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $summary = $this->auditService->summary(
            entity: $validated['entity'] ?? null,
            days: $validated['days'] ?? 30,
        );

        return app(SuccessResponse::class, ['data' => $summary]);
    }
}
