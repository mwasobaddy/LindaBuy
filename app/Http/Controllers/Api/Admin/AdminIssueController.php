<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\Api\ErrorResponse;
use App\Http\Responses\Api\SuccessResponse;
use App\Models\OrderIssueReport;
use App\Services\AuditService;
use Illuminate\Http\Request;

class AdminIssueController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('manage-issues');

        $query = OrderIssueReport::with([
            'order.seller.user',
            'order.buyer',
            'agent.user',
        ]);

        $query->when($request->status, fn ($q, $v) => $q->where('status', $v));
        $query->when($request->order_id, fn ($q, $v) => $q->where('order_id', $v));
        $query->when($request->issue_type, fn ($q, $v) => $q->where('issue_type', $v));
        $query->when($request->date_from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v));
        $query->when($request->date_to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));

        $reports = $query->orderBy('created_at', 'desc')->paginate(20);

        return app(SuccessResponse::class, ['data' => $reports]);
    }

    public function resolve(Request $request, OrderIssueReport $issueReport)
    {
        $this->authorize('manage-issues');

        if (! in_array($issueReport->status, ['REPORTED', 'UNDER_REVIEW'])) {
            return app(ErrorResponse::class, [
                'message' => 'Issue report is already resolved or dismissed.',
                'status' => 422,
            ]);
        }

        $issueReport->update([
            'status' => 'RESOLVED',
            'resolved_at' => now(),
        ]);

        $this->auditService->log(
            action: 'admin.issue.resolved',
            entity: 'issue_report',
            entityId: $issueReport->id,
            details: [
                'order_id' => $issueReport->order_id,
                'issue_type' => $issueReport->issue_type,
            ],
            request: $request,
        );

        return app(SuccessResponse::class, [
            'data' => $issueReport->fresh()->load(['order.seller.user', 'agent.user']),
            'message' => 'Issue report resolved.',
        ]);
    }

    public function dismiss(Request $request, OrderIssueReport $issueReport)
    {
        $this->authorize('manage-issues');

        if (! in_array($issueReport->status, ['REPORTED', 'UNDER_REVIEW'])) {
            return app(ErrorResponse::class, [
                'message' => 'Issue report is already resolved or dismissed.',
                'status' => 422,
            ]);
        }

        $issueReport->update([
            'status' => 'DISMISSED',
            'resolved_at' => now(),
        ]);

        $this->auditService->log(
            action: 'admin.issue.dismissed',
            entity: 'issue_report',
            entityId: $issueReport->id,
            details: [
                'order_id' => $issueReport->order_id,
                'issue_type' => $issueReport->issue_type,
            ],
            request: $request,
        );

        return app(SuccessResponse::class, [
            'data' => $issueReport->fresh()->load(['order.seller.user', 'agent.user']),
            'message' => 'Issue report dismissed.',
        ]);
    }
}
