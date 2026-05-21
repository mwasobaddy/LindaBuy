import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PhoneInput from '@/components/phone-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/hooks/use-initials';
import { edit } from '@/routes/profile';
import { useRef, useState } from 'react';

export default function Profile({
    status,
}: {
    status?: string;
}) {
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [avatarPreview, setAvatarPreview] = useState<string | null>(null);

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile information"
                    description="Update your name, email, and profile photo"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                        forceFormData: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors, setData, data }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="avatar">Profile photo</Label>

                                <div className="flex items-center gap-4">
                                    <Avatar className="h-16 w-16 overflow-hidden rounded-full">
                                        <AvatarImage
                                            src={avatarPreview ?? auth.user.avatar}
                                            alt={auth.user.name}
                                        />
                                        <AvatarFallback className="rounded-full text-lg">
                                            {getInitials(auth.user.name)}
                                        </AvatarFallback>
                                    </Avatar>

                                    <div className="flex flex-col gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => fileInputRef.current?.click()}
                                        >
                                            {auth.user.avatar ? 'Change photo' : 'Upload photo'}
                                        </Button>

                                        <Input
                                            ref={fileInputRef}
                                            id="avatar"
                                            type="file"
                                            name="avatar"
                                            accept="image/jpeg,image/png,image/webp"
                                            className="hidden"
                                            onChange={(e) => {
                                                const file = e.target.files?.[0];
                                                if (file) {
                                                    setData('avatar', file);
                                                    const reader = new FileReader();
                                                    reader.onloadend = () => {
                                                        setAvatarPreview(reader.result as string);
                                                    };
                                                    reader.readAsDataURL(file);
                                                }
                                            }}
                                        />

                                        {data.avatar instanceof File && (
                                            <span className="text-sm text-muted-foreground">
                                                {data.avatar.name}
                                            </span>
                                        )}
                                    </div>
                                </div>

                                <InputError
                                    className="mt-2"
                                    message={errors.avatar}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email ?? ''}
                                    name="email"
                                    autoComplete="email"
                                    placeholder="Email address"
                                />

                                {auth.user.email_verified_at ? (
                                    <p className="text-xs text-green-600 dark:text-green-400">
                                        Verified
                                    </p>
                                ) : null}

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">Mobile number</Label>

                                <PhoneInput
                                    id="phone"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.phone}
                                    name="phone"
                                    disabled
                                    required
                                    autoComplete="tel"
                                />

                                <p className="text-xs text-muted-foreground">
                                    Phone number cannot be changed. Contact support for assistance.
                                </p>

                                <InputError
                                    className="mt-2"
                                    message={errors.phone}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
