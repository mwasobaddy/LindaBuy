<?php

namespace App\Services;

use AfricasTalking\SDK\AfricasTalking;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function resend(User|string $identifier, string $type): string
    {
        $phone = $identifier instanceof User ? $identifier->phone : $identifier;

        $record = Otp::where('phone', $phone)
            ->where('type', $type)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($record) {
            $plaintext = Crypt::decryptString($record->otp);
            $this->send($phone, $plaintext);

            Log::info('OTP resent (existing)', ['phone' => $phone, 'type' => $type, 'record_id' => $record->id]);

            return $plaintext;
        }

        Log::info('OTP no valid existing record, generating new', ['phone' => $phone, 'type' => $type]);

        return $this->generate($identifier, $type);
    }

    public function generate(User|string $identifier, string $type): string
    {
        $phone = $identifier instanceof User ? $identifier->phone : $identifier;
        $userId = $identifier instanceof User ? $identifier->id : null;

        Otp::where('phone', $phone)
            ->where('type', $type)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otp = (string) random_int(100000, 999999);

        Otp::create([
            'user_id' => $userId,
            'phone' => $phone,
            'otp' => Crypt::encryptString($otp),
            'type' => $type,
            'expires_at' => now()->addMinutes(10),
        ]);

        return $otp;
    }

    public function verify(string $phone, string $otp, string $type): array
    {
        Log::info('OtpService::verify called', ['phone' => $phone, 'type' => $type]);

        $record = Otp::where('phone', $phone)
            ->where('type', $type)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        Log::info('OtpService::verify record lookup', [
            'found' => $record ? true : false,
            'record_id' => $record?->id,
            'record_type' => $record?->type,
            'expires_at' => $record?->expires_at?->toDateTimeString(),
            'used_at' => $record?->used_at?->toDateTimeString(),
            'attempts' => $record?->attempts,
        ]);

        if (! $record) {
            // Debug: check what records exist for this phone
            $all = DB::table('otps')
                ->where('phone', $phone)
                ->where('type', $type)
                ->orderBy('id', 'desc')
                ->get(['id', 'type', 'expires_at', 'used_at', 'attempts', 'created_at']);

            Log::info('OtpService::verify all matching records', $all->toArray());

            return ['success' => false, 'reason' => 'no_otp'];
        }

        if ($record->isExpired()) {
            $record->update(['used_at' => now()]);

            Log::info('OtpService::verify expired', ['record_id' => $record->id, 'expires_at' => $record->expires_at->toDateTimeString()]);

            return ['success' => false, 'reason' => 'expired'];
        }

        if ($record->isMaxAttemptsReached()) {
            Log::info('OtpService::verify max attempts reached', ['record_id' => $record->id, 'attempts' => $record->attempts]);

            return ['success' => false, 'reason' => 'max_attempts'];
        }

        $storedOtp = $record->otp;

        if (str_starts_with($storedOtp, '$2y$')) {
            $match = Hash::check($otp, $storedOtp);
        } else {
            try {
                $plaintext = Crypt::decryptString($storedOtp);
                $match = $plaintext === $otp;
            } catch (DecryptException $e) {
                $match = false;
            }
        }

        if (! $match) {
            Log::info('OtpService::verify hash check failed', ['record_id' => $record->id]);

            $record->increment('attempts');

            if ($record->isMaxAttemptsReached()) {
                $record->update(['used_at' => now()]);
            }

            $remaining = 3 - $record->fresh()->attempts;

            return ['success' => false, 'reason' => 'invalid', 'remaining' => $remaining];
        }

        Log::info('OtpService::verify success', ['record_id' => $record->id]);

        $record->update(['used_at' => now()]);

        return ['success' => true];
    }

    public function send(string $phone, string $otp): void
    {
        Log::info("OTP for {$phone}: {$otp}");

        if (config('services.sms.driver') === 'africastalking') {
            $this->sendViaAfricasTalking($phone, $otp);
        }
    }

    protected function sendViaAfricasTalking(string $phone, string $otp): void
    {
        $username = config('services.sandbox') ? 'sandbox' : config('services.africastalking.username');
        $apiKey = config('services.africastalking.api_key');

        $client = new AfricasTalking($username, $apiKey);

        $message = "Your LindaBuy verification code is: {$otp}. It expires in 10 minutes.";

        $client->sms()->send([
            'to' => '+254'.$phone,
            'message' => $message,
        ]);
    }
}
