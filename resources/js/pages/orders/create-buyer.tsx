import { Head, usePage, router } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function CreateBuyerOrder() {
    const { data, setData, post, processing, errors } = useForm({
        seller_id: '',
        item_description: '',
        price: '',
        delivery_type: 'shop_delivery',
        delivery_location: '',
    });

    const [message, setMessage] = useState('');
    const [checkoutId, setCheckoutId] = useState('');

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setMessage('');

        post('/api/orders/buyer-initiated', {
            onSuccess: (page) => {
                const responseData = (page as any).props?.flash?.data;
                setMessage('Order created! Check your phone for M-Pesa PIN.');
            },
            onError: () => {
                setMessage('Failed to create order. Check the form for errors.');
            },
        });
    };

    useEffect(() => {
        if (!checkoutId) return;

        const interval = setInterval(async () => {
            try {
                const res = await fetch(`/api/wallet/status/${checkoutId}`);
                const data = await res.json();
                if (data.data?.status === 'completed') {
                    setMessage('Payment successful!');
                    setCheckoutId('');
                    clearInterval(interval);
                    router.visit('/orders');
                } else if (data.data?.status === 'failed') {
                    setMessage('Payment failed. Please try again.');
                    setCheckoutId('');
                    clearInterval(interval);
                }
            } catch {
                // ignore polling errors
            }
        }, 3000);

        return () => clearInterval(interval);
    }, [checkoutId]);

    return (
        <>
            <Head title="Create Order as Buyer" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Create Order as Buyer"
                    description="Create an order to buy from a seller"
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="seller_id">Seller ID</Label>
                        <Input
                            id="seller_id"
                            type="number"
                            value={data.seller_id}
                            onChange={(e) => setData('seller_id', e.target.value)}
                            required
                            placeholder="Enter seller ID"
                        />
                        <InputError message={errors.seller_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="item_description">Item Description</Label>
                        <textarea
                            id="item_description"
                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            value={data.item_description}
                            onChange={(e) => setData('item_description', e.target.value)}
                            required
                            placeholder="Describe the item you want to buy"
                        />
                        <InputError message={errors.item_description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="price">Price (KES)</Label>
                        <Input
                            id="price"
                            type="number"
                            min="10"
                            value={data.price}
                            onChange={(e) => setData('price', e.target.value)}
                            required
                            placeholder="1000"
                        />
                        <InputError message={errors.price} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="delivery_type">Delivery Type</Label>
                        <select
                            id="delivery_type"
                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            value={data.delivery_type}
                            onChange={(e) => setData('delivery_type', e.target.value)}
                            required
                        >
                            <option value="shop_delivery">Shop Delivery</option>
                            <option value="g4s">G4S</option>
                        </select>
                        <InputError message={errors.delivery_type} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="delivery_location">Delivery Location (optional)</Label>
                        <Input
                            id="delivery_location"
                            value={data.delivery_location}
                            onChange={(e) => setData('delivery_location', e.target.value)}
                            placeholder="Nairobi, Kenya"
                        />
                        <InputError message={errors.delivery_location} />
                    </div>

                    {message && (
                        <p className={`text-sm ${message.includes('successful') ? 'text-green-600' : message.includes('Failed') ? 'text-red-600' : 'text-muted-foreground'}`}>
                            {message}
                        </p>
                    )}

                    <div className="flex items-center gap-4">
                        <Button disabled={processing}>
                            {processing ? 'Processing...' : 'Create & Pay'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

CreateBuyerOrder.layout = {
    breadcrumbs: [
        {
            title: 'Orders',
            href: '/orders',
        },
        {
            title: 'Create as Buyer',
            href: '/orders/create/buyer',
        },
    ],
};
