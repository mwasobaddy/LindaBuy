<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ApproveRequest;
use App\Http\Requests\Agent\RejectRequest;
use App\Http\Requests\Agent\StoreAgentRequest;
use App\Http\Responses\Api\ErrorResponse;
use App\Http\Responses\Api\ForbiddenResponse;
use App\Http\Responses\Api\NotFoundResponse;
use App\Http\Responses\Api\SuccessResponse;
use App\Models\Agent;
use App\Models\KycVerification;
use App\Services\AuditService;
use App\Services\KycUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller
{
    public function __construct(
        protected KycUploadService $kycUploadService,
        protected AuditService $auditService,
    ) {}

    public function store(StoreAgentRequest $request)
    {
        $user = $request->user();

        if ($user->agent) {
            return app(ErrorResponse::class, ['message' => 'You already have an agent record.', 'status' => 422]);
        }

        if ($user->role !== 'buyer') {
            return app(ErrorResponse::class, ['message' => 'Only buyers can request agent upgrade.', 'status' => 422]);
        }

        return DB::transaction(function () use ($request, $user) {
            $kycPhotoPath = $this->kycUploadService->upload(
                $request->file('kyc_photo'),
                $user->id,
                'kyc_photo'
            );

            $idCopyPath = $this->kycUploadService->upload(
                $request->file('id_copy'),
                $user->id,
                'id_copy'
            );

            $kycVerification = KycVerification::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'id_number' => $request->id_number,
                    'kyc_photo_path' => $kycPhotoPath,
                    'id_copy_path' => $idCopyPath,
                    'kyc_status' => 'PENDING',
                    'submitted_at' => now(),
                    'approved_at' => null,
                    'rejected_at' => null,
                    'rejected_reason' => null,
                ]
            );

            $agent = Agent::create([
                'user_id' => $user->id,
                'kyc_status' => 'PENDING',
            ]);

            $user->update(['role' => 'agent:pending']);

            if ($request->wantsJson()) {
                return app(SuccessResponse::class, [
                    'data' => ['agent_id' => $agent->id],
                    'message' => 'Agent upgrade request submitted successfully.',
                    'status' => 201,
                ]);
            }

            return redirect()->back()->with('success', 'Agent upgrade request submitted successfully.');
        });
    }

    public function updateKyc(Request $request, Agent $agent)
    {
        if ($agent->user_id !== $request->user()->id) {
            return app(ForbiddenResponse::class, ['message' => 'You can only update your own agent record.']);
        }

        $validated = $request->validate([
            'id_number' => ['sometimes', 'string'],
            'kyc_photo' => ['sometimes', 'image', 'max:2048'],
            'id_copy' => ['sometimes', 'image', 'max:2048'],
        ]);

        $kycVerification = $agent->user->kycVerification;

        if ($kycVerification && isset($validated['id_number'])) {
            $kycVerification->update(['id_number' => $validated['id_number']]);
        }

        if ($kycVerification && $request->hasFile('kyc_photo')) {
            $path = $this->kycUploadService->upload($request->file('kyc_photo'), $agent->user_id, 'kyc_photo');
            $kycVerification->update(['kyc_photo_path' => $path]);
        }

        if ($kycVerification && $request->hasFile('id_copy')) {
            $path = $this->kycUploadService->upload($request->file('id_copy'), $agent->user_id, 'id_copy');
            $kycVerification->update(['id_copy_path' => $path]);
        }

        return app(SuccessResponse::class, [
            'data' => ['agent_id' => $agent->id],
            'message' => 'Agent KYC updated.',
        ]);
    }

    public function show(Request $request)
    {
        $agent = $request->user()->agent;

        if (! $agent) {
            return app(NotFoundResponse::class, ['message' => 'No agent record found.']);
        }

        $agent->load('user.kycVerification');

        return app(SuccessResponse::class, ['data' => $agent]);
    }

    public function pendingAgents(Request $request)
    {
        $agents = Agent::with('user')
            ->where('kyc_status', 'PENDING')
            ->orderBy('created_at', 'desc')
            ->get();

        return app(SuccessResponse::class, ['data' => $agents]);
    }

    public function allAgents(Request $request)
    {
        $agents = Agent::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return app(SuccessResponse::class, ['data' => $agents]);
    }

    public function approve(ApproveRequest $request, Agent $agent)
    {
        if ($agent->kyc_status !== 'PENDING') {
            return app(ErrorResponse::class, ['message' => 'Agent KYC is not in pending status.', 'status' => 422]);
        }

        return DB::transaction(function () use ($request, $agent) {
            $agent->update([
                'kyc_status' => 'APPROVED',
                'approved_at' => now(),
            ]);

            $user = $agent->user;
            $user->update(['role' => 'agent:approved']);
            $user->assignRole('agent');

            $this->auditService->log(
                action: 'admin.agent.approved',
                entity: 'agent',
                entityId: $agent->id,
                request: $request,
            );

            return app(SuccessResponse::class, ['message' => 'Agent approved successfully.']);
        });
    }

    public function reject(RejectRequest $request, Agent $agent)
    {
        if ($agent->kyc_status !== 'PENDING') {
            return app(ErrorResponse::class, ['message' => 'Agent KYC is not in pending status.', 'status' => 422]);
        }

        return DB::transaction(function () use ($request, $agent) {
            $agent->update([
                'kyc_status' => 'REJECTED',
                'rejected_reason' => $request->rejection_reason,
                'rejected_at' => now(),
            ]);

            $this->auditService->log(
                action: 'admin.agent.rejected',
                entity: 'agent',
                entityId: $agent->id,
                details: ['reason' => $request->rejection_reason],
                request: $request,
            );

            return app(SuccessResponse::class, ['message' => 'Agent rejected.']);
        });
    }

    public function toggleAgentStatus(Request $request, Agent $agent)
    {
        $currentStatus = $agent->kyc_status;

        if (! in_array($currentStatus, ['APPROVED', 'REJECTED'])) {
            return app(ErrorResponse::class, ['message' => 'Only approved or rejected agents can be toggled.', 'status' => 422]);
        }

        return DB::transaction(function () use ($request, $agent, $currentStatus) {
            $newStatus = $currentStatus === 'APPROVED' ? 'REJECTED' : 'APPROVED';
            $agent->update(['kyc_status' => $newStatus]);

            $user = $agent->user;

            if ($newStatus === 'APPROVED') {
                $user->update(['role' => 'agent:approved']);
                $user->assignRole('agent');
            } else {
                if ($user->role === 'agent:approved') {
                    $user->update(['role' => 'agent:pending']);
                    $user->removeRole('agent');
                }
            }

            $this->auditService->log(
                action: 'admin.agent.status_toggled',
                entity: 'agent',
                entityId: $agent->id,
                details: ['new_status' => $newStatus],
                request: $request,
            );

            return app(SuccessResponse::class, ['message' => "Agent status toggled to {$newStatus}."]);
        });
    }
}
