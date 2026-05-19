<?php

namespace App\Http\Controllers\Api;

use App\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ApproveKycRequest;
use App\Http\Requests\Seller\ApproveShopRequest;
use App\Http\Requests\Seller\RejectKycRequest;
use App\Http\Requests\Seller\RejectShopRequest;
use App\Http\Requests\Seller\StoreSellerRequest;
use App\Models\KycVerification;
use App\Models\Seller;
use App\Services\KycUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerController extends Controller
{
    public function __construct(
        protected KycUploadService $kycUploadService
    ) {}

    public function store(StoreSellerRequest $request)
    {
        $user = $request->user();

        if ($user->kycVerification && $user->kycVerification->kyc_status === 'PENDING') {
            return ApiResponse::error('You already have a pending KYC verification.', 422);
        }

        if ($user->kycVerification && $user->kycVerification->kyc_status === 'REJECTED') {
            $hoursSinceRejection = $user->kycVerification->rejected_at->diffInHours(now());
            if ($hoursSinceRejection < 24) {
                return ApiResponse::error(
                    'You can resubmit after 24 hours from rejection.',
                    422
                );
            }
        }

        if ($user->role !== 'buyer') {
            return ApiResponse::error('Only buyers can request seller upgrade.', 422);
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
                return ApiResponse::success(
                    ['seller_id' => $seller->id],
                    'Seller upgrade request submitted successfully.',
                    201
                );
            }

            return redirect()->back()->with('success', 'Seller upgrade request submitted successfully.');
        });
    }

    public function myShops(Request $request)
    {
        $user = $request->user();
        $sellers = $user->sellers()->with('user.kycVerification')->get();

        return ApiResponse::success($sellers);
    }

    public function update(Request $request, Seller $seller)
    {
        if ($seller->user_id !== $request->user()->id) {
            return ApiResponse::forbidden('You can only update your own shops.');
        }

        $validated = $request->validate([
            'shop_name' => ['sometimes', 'string', 'max:255'],
            'shop_location' => ['sometimes', 'string', 'max:255'],
            'shop_location_coords_lat' => ['sometimes', 'numeric', 'between:-90,90'],
            'shop_location_coords_lng' => ['sometimes', 'numeric', 'between:-180,180'],
        ]);

        $seller->update($validated);

        return ApiResponse::success(['seller_id' => $seller->id], 'Shop details updated.');
    }

    public function pendingSellers(Request $request)
    {
        $sellers = Seller::with('user.kycVerification')
            ->where('verification_status', 'PENDING')
            ->orWhereHas('user.kycVerification', fn ($q) => $q->where('kyc_status', 'PENDING'))
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::success($sellers);
    }

    public function allSellers(Request $request)
    {
        $sellers = Seller::with('user.kycVerification')
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::success($sellers);
    }

    public function approveKyc(ApproveKycRequest $request, Seller $seller)
    {
        $kyc = $seller->user->kycVerification;

        if (! $kyc || $kyc->kyc_status !== 'PENDING') {
            return ApiResponse::error('KYC is not in pending status.', 422);
        }

        return DB::transaction(function () use ($seller) {
            $kyc = $seller->user->kycVerification;
            $kyc->update([
                'kyc_status' => 'APPROVED',
                'approved_at' => now(),
            ]);

            $this->checkAndAssignFullSellerApproval($seller);

            return ApiResponse::success([], 'KYC approved successfully.');
        });
    }

    public function rejectKyc(RejectKycRequest $request, Seller $seller)
    {
        $kyc = $seller->user->kycVerification;

        if (! $kyc || $kyc->kyc_status !== 'PENDING') {
            return ApiResponse::error('KYC is not in pending status.', 422);
        }

        return DB::transaction(function () use ($request, $seller) {
            $kyc = $seller->user->kycVerification;
            $kyc->update([
                'kyc_status' => 'REJECTED',
                'rejected_reason' => $request->rejection_reason,
                'rejected_at' => now(),
            ]);

            return ApiResponse::success([], 'KYC rejected.');
        });
    }

    public function approveShop(ApproveShopRequest $request, Seller $seller)
    {
        if ($seller->verification_status !== 'PENDING') {
            return ApiResponse::error('Shop is not in pending status.', 422);
        }

        return DB::transaction(function () use ($seller) {
            $seller->update([
                'verification_status' => 'APPROVED',
                'approved_at' => now(),
            ]);

            $this->checkAndAssignFullSellerApproval($seller);

            return ApiResponse::success([], 'Shop approved successfully.');
        });
    }

    public function rejectShop(RejectShopRequest $request, Seller $seller)
    {
        if ($seller->verification_status !== 'PENDING') {
            return ApiResponse::error('Shop is not in pending status.', 422);
        }

        return DB::transaction(function () use ($request, $seller) {
            $seller->update([
                'verification_status' => 'REJECTED',
                'rejected_reason' => $request->rejection_reason,
                'rejected_at' => now(),
            ]);

            return ApiResponse::success([], 'Shop rejected.');
        });
    }

    public function toggleSellerStatus(Request $request, Seller $seller)
    {
        $currentStatus = $seller->verification_status;

        if (! in_array($currentStatus, ['APPROVED', 'REJECTED'])) {
            return ApiResponse::error('Only approved or rejected sellers can be toggled.', 422);
        }

        return DB::transaction(function () use ($seller, $currentStatus) {
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

            return ApiResponse::success([], "Seller status toggled to {$newStatus}.");
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
