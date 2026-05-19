<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\OtpSentResponse;
use App\Http\Responses\OtpVerifiedResponse;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class OtpController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    public function send(Request $request): OtpSentResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^[17]\d{8}$/'],
        ]);

        $phone = $request->input('phone');

        $this->checkRateLimit('otp-send:'.$phone, 5, 10);

        Log::info('OTP send (phone_verification)', ['phone' => $phone]);

        $this->otpService->resend($phone, 'phone_verification');

        $request->session()->put('otp_verify_phone', $phone);
        $request->session()->put('otp_verify_type', 'phone_verification');
        $request->session()->put('otp_verify_reason', $request->session()->get('otp_verify_reason', ''));

        return new OtpSentResponse;
    }

    public function sendLogin(Request $request): JsonResponse|OtpSentResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^[17]\d{8}$/'],
        ]);

        $phone = $request->input('phone');

        $this->checkRateLimit('otp-login:'.$phone, 5, 10);

        $user = User::where('phone', $phone)->first();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'No account found with this number']);
        }

        Log::info('OTP send (login)', ['phone' => $phone, 'user_id' => $user->id]);

        $this->otpService->resend($user, 'login');

        $request->session()->put('otp_verify_phone', $phone);
        $request->session()->put('otp_verify_type', 'login');
        $request->session()->put('otp_verify_reason', '');

        return new OtpSentResponse(redirect: '/auth/otp-verify');
    }

    public function verify(Request $request): JsonResponse|OtpVerifiedResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^[17]\d{8}$/'],
            'otp' => ['required', 'string', 'size:6'],
            'type' => ['required', 'string', 'in:phone_verification,login'],
        ]);

        $phone = $request->input('phone');
        $otp = $request->input('otp');
        $type = $request->input('type');

        Log::info('OTP verify attempt', ['phone' => $phone, 'type' => $type]);

        $this->checkRateLimit('otp-verify:'.$phone, 5, 10);

        $result = $this->otpService->verify($phone, $otp, $type);

        Log::info('OTP verify result', $result);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'reason' => $result['reason'],
                'remaining' => $result['remaining'] ?? null,
            ], 422);
        }

        $user = User::where('phone', $phone)->firstOrFail();

        Log::info('OTP verify user found', ['id' => $user->id, 'phone' => $user->phone, 'mobile_verified_at' => $user->mobile_verified_at]);

        if (! $user->hasVerifiedMobile()) {
            $user->markMobileAsVerified();
            Log::info('OTP verify marked mobile as verified', ['phone' => $phone, 'type' => $type]);
        }

        Auth::login($user, true);

        $request->session()->regenerate();

        $request->session()->forget(['otp_verify_phone', 'otp_verify_type', 'otp_verify_reason']);

        $redirectUrl = redirect()->intended(Fortify::redirects('login'))->getTargetUrl();

        $toastMessage = $type === 'login' ? 'Logged in successfully!' : 'Mobile number verified!';

        return new OtpVerifiedResponse($redirectUrl, $toastMessage);
    }

    protected function checkRateLimit(string $key, int $maxAttempts, int $decayMinutes): void
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'phone' => ['Too many attempts. Try again in '.ceil($seconds / 60).' minutes.'],
            ]);
        }

        RateLimiter::hit($key, $decayMinutes * 60);
    }
}
