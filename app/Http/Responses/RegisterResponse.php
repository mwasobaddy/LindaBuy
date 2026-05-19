<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false], 201);
        }

        $phone = $request->user()?->phone;

        if ($phone) {
            $request->session()->put('otp_verify_phone', $phone);
            $request->session()->put('otp_verify_type', 'phone_verification');
            $request->session()->put('otp_verify_reason', 'registration');

            return redirect()->to('/auth/otp-verify');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account created successfully!')]);

        return redirect()->intended(config('fortify.home'));
    }
}
