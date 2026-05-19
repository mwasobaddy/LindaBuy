import { Head, usePage } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function SellerRequest() {
    const { auth } = usePage().props;

    const { data, setData, post, processing, errors } = useForm({
        shop_name: '',
        shop_location: '',
        shop_location_coords_lat: '',
        shop_location_coords_lng: '',
        id_number: '',
        kyc_photo: null as File | null,
        id_copy: null as File | null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/api/sellers/request', {
            forceFormData: true,
        });
    };

    return (
        <>
            <Head title="Become a Seller" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Become a Seller"
                    description="Submit your shop and KYC details for review"
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="shop_name">Shop Name</Label>
                        <Input
                            id="shop_name"
                            value={data.shop_name}
                            onChange={(e) => setData('shop_name', e.target.value)}
                            required
                            placeholder="My Shop"
                        />
                        <InputError message={errors.shop_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="shop_location">Shop Location (optional)</Label>
                        <Input
                            id="shop_location"
                            value={data.shop_location}
                            onChange={(e) => setData('shop_location', e.target.value)}
                            placeholder="Nairobi, Kenya"
                        />
                        <InputError message={errors.shop_location} />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="shop_location_coords_lat">Latitude (optional)</Label>
                            <Input
                                id="shop_location_coords_lat"
                                type="number"
                                step="any"
                                value={data.shop_location_coords_lat}
                                onChange={(e) => setData('shop_location_coords_lat', e.target.value)}
                                placeholder="-1.2921"
                            />
                            <InputError message={errors.shop_location_coords_lat} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="shop_location_coords_lng">Longitude (optional)</Label>
                            <Input
                                id="shop_location_coords_lng"
                                type="number"
                                step="any"
                                value={data.shop_location_coords_lng}
                                onChange={(e) => setData('shop_location_coords_lng', e.target.value)}
                                placeholder="36.8219"
                            />
                            <InputError message={errors.shop_location_coords_lng} />
                        </div>
                    </div>

                    <hr className="border-t border-sidebar-border/70" />

                    <Heading
                        variant="small"
                        title="Identity Verification (KYC)"
                        description="Upload your ID documents for verification"
                    />

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

SellerRequest.layout = {
    breadcrumbs: [
        {
            title: 'Become a Seller',
            href: '/seller/request',
        },
    ],
};
