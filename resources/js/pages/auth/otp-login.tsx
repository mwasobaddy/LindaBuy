import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthMethodTabs from '@/components/auth-method-tabs';
import type {AuthMethodTab} from '@/components/auth-method-tabs';
import InputError from '@/components/input-error';
import PhoneInput from '@/components/phone-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';

type Props = {
    canRegister: boolean;
};

const authTabs: AuthMethodTab[] = [
    { id: 'password', label: 'Password', href: '/login' },
    { id: 'otp', label: 'OTP Code', href: '/auth/otp-login' },
];

export default function OtpLogin({ canRegister }: Props) {
    const [phone, setPhone] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');

    const validatePhone = (value: string): boolean => {
        const digits = value.replace(/\D/g, '');

        return digits.length === 9 && (digits[0] === '1' || digits[0] === '7');
    };

    const isFormValid = validatePhone(phone);

    const handleSend = async () => {
        if (!isFormValid || processing) return;

        setProcessing(true);
        setError('');

        try {
            const response = await fetch('/auth/otp/send-login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({ phone }),
            });

            const data = await response.json();

            if (data.success) {
                router.visit(data.redirect);
            } else {
                setError(data.message || 'Failed to send code');
            }
        } catch {
            setError('Something went wrong. Please try again.');
        } finally {
            setProcessing(false);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && isFormValid) {
            handleSend();
        }
    };

    return (
        <>
            <Head title="Log in with code" />

            <div className="flex flex-col gap-6">
                <AuthMethodTabs tabs={authTabs} activeTab="otp" />

                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <label htmlFor="phone">Mobile number</label>
                        <PhoneInput
                            id="phone"
                            name="phone"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="tel"
                            value={phone}
                            onChange={setPhone}
                            onKeyDown={handleKeyDown}
                        />
                        <InputError message={error} />
                    </div>

                    <Button
                        type="button"
                        variant="submit"
                        className="mt-4 w-full"
                        tabIndex={2}
                        disabled={!isFormValid || processing}
                        data-test="otp-send-button"
                        onClick={handleSend}
                    >
                        {processing && <Spinner />}
                        Send verification code
                    </Button>
                </div>

                {canRegister && (
                    <div className="text-center text-sm text-muted-foreground">
                        Don't have an account?{' '}
                        <TextLink href={register()} tabIndex={4}>
                            Sign up
                        </TextLink>
                    </div>
                )}
            </div>
        </>
    );
}

OtpLogin.layout = {
    title: 'Log in with verification code',
    description: 'Enter your mobile number to receive a one-time code',
};
