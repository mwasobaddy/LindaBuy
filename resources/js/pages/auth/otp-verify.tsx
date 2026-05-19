import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    phone: string;
    type?: string;
    reason?: string;
};

function maskPhone(phone: string): string {
    if (phone.length !== 9) return phone;

    return phone.slice(0, 2) + '****' + phone.slice(6);
}

export default function OtpVerify({ phone, type = 'login', reason }: Props) {
    const [otp, setOtp] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    const [cooldown, setCooldown] = useState(0);
    const cooldownRef = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        if (cooldown > 0) {
            const interval = setInterval(() => {
                setCooldown((prev) => {
                    if (prev <= 1) {
                        clearInterval(interval);

                        return 0;
                    }

                    return prev - 1;
                });
            }, 1000);

            cooldownRef.current = interval;
        }

        return () => {
            if (cooldownRef.current) {
                clearInterval(cooldownRef.current);
            }
        };
    }, [cooldown]);

    const handleVerify = async (code: string) => {
        if (code.length !== 6 || processing) return;

        setProcessing(true);
        setError('');

        try {
            const response = await fetch('/auth/otp/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({ phone, otp: code, type }),
            });

            const data = await response.json();

            if (data.success) {
                toast.success(type === 'login' ? 'Logged in successfully!' : 'Mobile number verified!');
                router.visit(data.redirect);
            } else {
                setError(getErrorMessage(data));
                setOtp('');
            }
        } catch {
            setError('Something went wrong. Please try again.');
            setOtp('');
        } finally {
            setProcessing(false);
        }
    };

    const handleResend = async () => {
        if (cooldown > 0 || processing) return;

        setCooldown(30);
        setError('');

        const endpoint = type === 'login' ? '/auth/otp/send-login' : '/auth/otp/send';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({ phone }),
            });

            const data = await response.json();

            if (!data.success) {
                setError(data.message || 'Failed to resend code');
                setCooldown(0);
            }
        } catch {
            setError('Failed to resend code');
            setCooldown(0);
        }
    };

    const handleOtpChange = (value: string) => {
        setOtp(value);
        setError('');

        if (value.length === 6) {
            handleVerify(value);
        }
    };

    const getErrorMessage = (data: { reason?: string; remaining?: number }): string => {
        switch (data.reason) {
            case 'no_otp':
                return 'No verification code found. Request a new one.';
            case 'expired':
                return 'This code has expired. Request a new one.';
            case 'max_attempts':
                return 'Too many incorrect attempts. Request a new code.';
            case 'invalid':
                return `Incorrect code. ${data.remaining ?? 0} attempt(s) remaining.`;
            default:
                return 'Verification failed. Please try again.';
        }
    };

    return (
        <>
            <Head title="Enter verification code" />

            <div className="flex flex-col gap-6">
                {reason === 'verify' && (
                    <div className="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                        Your phone number is not yet verified. Please verify it below.
                    </div>
                )}

                {reason === 'registration' && (
                    <div className="rounded-lg bg-blue-50 p-3 text-sm text-blue-800 dark:bg-blue-900/30 dark:text-blue-200">
                        Almost there! Verify your phone number to get started.
                    </div>
                )}

                <div className="text-center text-sm text-muted-foreground">
                    We sent a 6-digit code to <span className="font-medium text-foreground">+254 {maskPhone(phone)}</span>
                </div>

                <div className="flex justify-center">
                    <InputOTP
                        maxLength={6}
                        value={otp}
                        onChange={(value: string) => handleOtpChange(value)}
                        disabled={processing}
                    >
                        <InputOTPGroup>
                            <InputOTPSlot index={0} />
                            <InputOTPSlot index={1} />
                            <InputOTPSlot index={2} />
                            <InputOTPSlot index={3} />
                            <InputOTPSlot index={4} />
                            <InputOTPSlot index={5} />
                        </InputOTPGroup>
                    </InputOTP>
                </div>

                <InputError message={error} className="text-center" />

                {processing && (
                    <div className="flex justify-center">
                        <Spinner className="size-5" />
                    </div>
                )}

                <div className="text-center text-sm text-muted-foreground">
                    {cooldown > 0 ? (
                        <span>Resend code in {cooldown}s</span>
                    ) : (
                        <button
                            type="button"
                            className="text-primary underline-offset-4 hover:underline"
                            onClick={handleResend}
                            disabled={processing}
                        >
                            Resend code
                        </button>
                    )}
                </div>

                <div className="text-center text-sm text-muted-foreground">
                    <TextLink href="/auth/otp-login">
                        Change phone number
                    </TextLink>
                </div>
            </div>
        </>
    );
}
