import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import AuthMethodTabs from '@/components/auth-method-tabs';
import type {AuthMethodTab} from '@/components/auth-method-tabs';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PhoneInput from '@/components/phone-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
};

const authTabs: AuthMethodTab[] = [
    { id: 'password', label: 'Password', href: '/login' },
    { id: 'otp', label: 'OTP Code', href: '/auth/otp-login' },
];

export default function Login({
    status,
    canResetPassword,
    canRegister,
}: Props) {
    const [phone, setPhone] = useState('');
    const [password, setPassword] = useState('');

    const validatePhone = (value: string): boolean => {
        const digits = value.replace(/\D/g, '');

        return digits.length === 9 && (digits[0] === '1' || digits[0] === '7');
    };

    const isFormValid = validatePhone(phone) && password.length > 0;

    return (
        <>
            <Head title="Log in" />

            <div className="flex flex-col gap-6">
                <AuthMethodTabs tabs={authTabs} activeTab="password" />

                <Form
                    {...store.form()}
                    resetOnSuccess={['password']}
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => {
                        return (
                        <>
                            <div className="grid gap-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Mobile number</Label>
                                    <PhoneInput
                                        id="phone"
                                        name="phone"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="tel"
                                        value={phone}
                                        onChange={setPhone}
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center">
                                        <Label htmlFor="password">Password</Label>
                                        {canResetPassword && (
                                            <TextLink
                                                href={request()}
                                                className="ml-auto text-sm"
                                                tabIndex={5}
                                            >
                                                Forgot password?
                                            </TextLink>
                                        )}
                                    </div>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="Password"
                                        value={password}
                                        onChange={(e) => setPassword(e.target.value)}
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="flex items-center space-x-3">
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        tabIndex={3}
                                    />
                                    <Label htmlFor="remember">Remember me</Label>
                                </div>

                                <Button
                                    type="submit"
                                    variant="submit"
                                    className="mt-4 w-full"
                                    tabIndex={4}
                                    disabled={!isFormValid || processing}
                                    data-test="login-button"
                                >
                                    {processing && <Spinner />}
                                    Log in
                                </Button>
                            </div>

                            {canRegister && (
                                <div className="text-center text-sm text-muted-foreground">
                                    Don't have an account?{' '}
                                    <TextLink href={register()} tabIndex={5}>
                                        Sign up
                                    </TextLink>
                                </div>
                            )}
                        </>
                    );
                    }}
                </Form>

                {status && (
                    <div className="mb-4 text-center text-sm font-medium text-green-600">
                        {status}
                    </div>
                )}
            </div>
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your mobile number and password below to log in',
};
