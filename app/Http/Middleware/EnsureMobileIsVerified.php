<?php

namespace App\Http\Middleware;

use App\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->hasVerifiedMobile()) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return ApiResponse::forbidden('Mobile number not verified.');
        }

        if ($request->user()) {
            $request->session()->put('otp_verify_phone', $request->user()->phone);
            $request->session()->put('otp_verify_type', 'phone_verification');
            $request->session()->put('otp_verify_reason', 'verify');
        }

        return redirect()->route('auth.otp.verify.page');
    }
}
