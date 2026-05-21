<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ApproveKycRequest;
use App\Http\Requests\Seller\ApproveShopRequest;
use App\Http\Requests\Seller\RejectKycRequest;
use App\Http\Requests\Seller\RejectShopRequest;
use App\Http\Requests\Seller\StoreSellerRequest;
use App\Http\Responses\Api\ErrorResponse;
use App\Http\Responses\Api\ForbiddenResponse;
use App\Http\Responses\Api\SuccessResponse;
use App\Models\KycVerification;
use App\Models\Seller;
use App\Services\AuditService;
use App\Services\KycUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerController extends Controller
{
    public function __construct(
        protected KycUploadService $kycUploadService,
        protected AuditService $auditService,
    ) {}

    public function store(StoreSellerRequest $request)
    {
        $user = $request->user();

        if ($user->kycVerification && $user->kycVerification->kyc_status === 'PENDING') {
            return app(ErrorResponse::class, ['message' => 'You already have a pending KYC verification.', 'status' => 422]);
        }

        if ($user->kycVerification && $user->kycVerification->kyc_status === 'REJECTED') {
            $hoursSinceRejection = $user->kycVerification->rejected_at->diffInHours(now());
            if ($hoursSinceRejection < 24) {
                return app(ErrorResponse::class, ['message' => 'You can resubmit after 24 hours from rejection.', 'status' => 422]);
            }
        }

        if ($user->role !== 'buyer') {
            return app(ErrorResponse::class, ['message' => 'Only buyers can request seller upgrade.', 'status' => 422]);
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

            $seller = Seller::create([
                'user_id' => $user->id,
                'shop_name' => $request->shop_name,
                'shop_location' => $request->shop_location,
                'shop_location_coords_lat' => $request->shop_location_coords_lat,
                'shop_location_coords_lng' => $request->shop_location_coords_lng,
                'verification_status' => 'PENDING',
            ]);

            $user->update(['role' => 'seller:pending']);

            if ($request->wantsJson()) {
                return app(SuccessResponse::class, [
                    'data' => ['seller_id' => $seller->id],
                    'message' => 'Seller upgrade request submitted successfully.',
                    'status' => 201,
                ]);
            }

            return redirect()->back()->with('success', 'Seller upgrade request submitted successfully.');
        });
    }

    public function myShops(Request $request)
    {
        $user = $request->user();
        $sellers = $user->sellers()->with('user.kycVerification')->get();

        return app(SuccessResponse::class, ['data' => $sellers]);
    }

    public function update(Request $request, Seller $seller)
    {
        if ($seller->user_id !== $request->user()->id) {
            return app(ForbiddenResponse::class, ['message' => 'You can only update your own shops.']);
        }

        $validated = $request->validate([
            'shop_name' => ['sometimes', 'string', 'max:255'],
            'shop_location' => ['sometimes', 'string', 'max:255'],
            'shop_location_coords_lat' => ['sometimes', 'numeric', 'between:-90,90'],
            'shop_location_coords_lng' => ['sometimes', 'numeric', 'between:-180,180'],
        ]);

        $seller->update($validated);

        return app(SuccessResponse::class, [
            'data' => ['seller_id' => $seller->id],
            'message' => 'Shop details updated.',
        ]);
    }

    public function pendingSellers(Request $request)
    {
        $sellers = Seller::with('user.kycVerification')
            ->where('verification_status', 'PENDING')
            ->orWhereHas('user.kycVerification', fn ($q) => $q->where('kyc_status', 'PENDING'))
            ->orderBy('created_at', 'desc')
            ->get();

        return app(SuccessResponse::class, ['data' => $sellers]);
    }

    public function allSellers(Request $request)
    {
        $sellers = Seller::with('user.kycVerification')
            ->orderBy('created_at', 'desc')
            ->get();

        return app(SuccessResponse::class, ['data' => $sellers]);
    }

    public function approveKyc(ApproveKycRequest $request, Seller $seller)
    {
        $kyc = $seller->user->kycVerification;

        if (! $kyc || $kyc->kyc_status !== 'PENDING') {
            return app(ErrorResponse::class, ['message' => 'KYC is not in pending status.', 'status' => 422]);
        }

        return DB::transaction(function () use ($request, $seller) {
            $kyc = $seller->user->kycVerification;
            $kyc->update([
                'kyc_status' => 'APPROVED',
                'approved_at' => now(),
            ]);

            $this->checkAndAssignFullSellerApproval($seller);

            $this->auditService->log(
                action: 'admin.seller.kyc_approved',
                entity: 'seller',
                entityId: $seller->id,
                request: $request,
            );

            return app(SuccessResponse::class, ['message' => 'KYC approved successfully.']);
        });
    }

    public function rejectKyc(RejectKycRequest $request, Seller $seller)
    {
        $kyc = $seller->user->kycVerification;

        if (! $kyc || $kyc->kyc_status !== 'PENDING') {
            return app(ErrorResponse::class, ['message' => 'KYC is not in pending status.', 'status' => 422]);
        }

        return DB::transaction(function () use ($request, $seller) {
            $kyc = $seller->user->kycVerification;
            $kyc->update([
                'kyc_status' => 'REJECTED',
                'rejected_reason' => $request->rejection_reason,
                'rejected_at' => now(),
            ]);

            $this->auditService->log(
                action: 'admin.seller.kyc_rejected',
                entity: 'seller',
                entityId: $seller->id,
                details: ['reason' => $request->rejection_reason],
                request: $request,
            );

            return app(SuccessResponse::class, ['message' => 'KYC rejected.']);
        });
    }

    public function approveShop(ApproveShopRequest $request, Seller $seller)
    {
        if ($seller->verification_status !== 'PENDING') {
            return app(ErrorResponse::class, ['message' => 'Shop is not in pending status.', 'status' => 422]);
        }

        return DB::transaction(function () use ($request, $seller) {
            $seller->update([
                'verification_status' => 'APPROVED',
                'approved_at' => now(),
            ]);

            $this->checkAndAssignFullSellerApproval($seller);

            $this->auditService->log(
                action: 'admin.seller.shop_approved',
                entity: 'seller',
                entityId: $seller->id,
                request: $request,
            );

            return app(SuccessResponse::class, ['message' => 'Shop approved successfully.']);
        });
    }

    public function rejectShop(RejectShopRequest $request, Seller $seller)
    {
        if ($seller->verification_status !== 'PENDING') {
            return app(ErrorResponse::class, ['message' => 'Shop is not in pending status.', 'status' => 422]);
        }

        return DB::transaction(function () use ($request, $seller) {
            $seller->update([
                'verification_status' => 'REJECTED',
                'rejected_reason' => $request->rejection_reason,
                'rejected_at' => now(),
            ]);

            $this->auditService->log(
                action: 'admin.seller.shop_rejected',
                entity: 'seller',
                entityId: $seller->id,
                details: ['reason' => $request->rejection_reason],
                request: $request,
            );

            return app(SuccessResponse::class, ['message' => 'Shop rejected.']);
        });
    }

    public function toggleSellerStatus(Request $request, Seller $seller)
    {
        $currentStatus = $seller->verification_status;

        if (! in_array($currentStatus, ['APPROVED', 'REJECTED'])) {
            return app(ErrorResponse::class, ['message' => 'Only approved or rejected sellers can be toggled.', 'status' => 422]);
        }

        return DB::transaction(function () use ($request, $seller, $currentStatus) {
            $newStatus = $currentStatus === 'APPROVED' ? 'REJECTED' : 'APPROVED';
            $seller->update(['verification_status' => $newStatus]);

            if ($newStatus === 'APPROVED') {
                $this->checkAndAssignFullSellerApproval($seller);
            } else {
                $user = $seller->user;
                if ($user->role === 'seller:approved') {
                    $user->update(['role' => 'seller:pending']);
                    $user->removeRole('seller');
                }
            }

            $this->auditService->log(
                action: 'admin.seller.status_toggled',
                entity: 'seller',
                entityId: $seller->id,
                details: ['new_status' => $newStatus],
                request: $request,
            );

            return app(SuccessResponse::class, ['message' => "Seller status toggled to {$newStatus}."]);
        });
    }

    protected function checkAndAssignFullSellerApproval(Seller $seller): void
    {
        $kyc = $seller->user->kycVerification;
        $kycApproved = $kyc && $kyc->kyc_status === 'APPROVED';
        $shopApproved = $seller->verification_status === 'APPROVED';

        if ($kycApproved && $shopApproved) {
            $user = $seller->user;
            $user->update(['role' => 'seller:approved']);
            $user->assignRole('seller');
        }
    }
}
