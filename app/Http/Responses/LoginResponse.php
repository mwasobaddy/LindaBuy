<?php

namespace App\Http\Responses;

use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false], 200);
        }

        if ($user && ! $user->hasVerifiedMobile()) {
            $otpService = app(OtpService::class);
            $otp = $otpService->generate($user, 'phone_verification');
            $otpService->send($user->phone, $otp);

            $request->session()->put('otp_verify_phone', $user->phone);
            $request->session()->put('otp_verify_type', 'phone_verification');
            $request->session()->put('otp_verify_reason', 'verify');

            return redirect()->to('/auth/otp-verify');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome back!')]);

        return redirect()->intended(Fortify::redirects('login'));
    }
}
