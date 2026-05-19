import { Head, usePage } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function AgentRequest() {
    const { auth } = usePage().props;

    const { data, setData, post, processing, errors } = useForm({
        id_number: '',
        kyc_photo: null as File | null,
        id_copy: null as File | null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/api/agents/request', {
            forceFormData: true,
        });
    };

    return (
        <>
            <Head title="Become an Agent" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Become an Agent"
                    description="Submit your KYC details to become a verification agent"
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="id_number">ID Number</Label>
                        <Input
                            id="id_number"
                            value={data.id_number}
                            onChange={(e) => setData('id_number', e.target.value)}
                            required
                            placeholder="Enter your national ID number"
                        />
                        <InputError message={errors.id_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="kyc_photo">KYC Photo</Label>
                        <Input
                            id="kyc_photo"
                            type="file"
                            accept="image/*"
                            onChange={(e) => {
                                const file = (e.target as HTMLInputElement).files?.[0];
                                if (file) setData('kyc_photo', file);
                            }}
                            required
                        />
                        <InputError message={errors.kyc_photo} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="id_copy">ID Copy</Label>
                        <Input
                            id="id_copy"
                            type="file"
                            accept="image/*"
                            onChange={(e) => {
                                const file = (e.target as HTMLInputElement).files?.[0];
                                if (file) setData('id_copy', file);
                            }}
                            required
                        />
                        <InputError message={errors.id_copy} />
                    </div>

                    <div className="flex items-center gap-4">
                        <Button disabled={processing}>
                            Submit Request
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

AgentRequest.layout = {
    breadcrumbs: [
        {
            title: 'Become an Agent',
            href: '/agent/request',
        },
    ],
};
