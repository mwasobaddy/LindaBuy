import { Head, usePage, router } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function CreateSellerOrder() {
    const { templates, auth } = usePage().props as {
        templates?: { id: number; template_name: string; item_description: string; price: number; delivery_type: string; delivery_location: string | null }[];
        auth: { user?: { role?: string; permissions?: string[] } };
    };

    const { data, setData, post, processing, errors } = useForm({
        seller_id: 0,
        buyer_phone: '',
        item_description: '',
        price: '',
        delivery_type: 'shop_delivery',
        delivery_location: '',
    });

    const [message, setMessage] = useState('');
    const templatesList = templates ?? [];

    const loadTemplate = (templateId: string) => {
        const tmpl = templatesList.find((t) => t.id === parseInt(templateId));
        if (tmpl) {
            setData({
                ...data,
                item_description: tmpl.item_description,
                price: String(tmpl.price),
                delivery_type: tmpl.delivery_type,
                delivery_location: tmpl.delivery_location ?? '',
            });
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setMessage('');

        post('/api/orders/seller-initiated', {
            onSuccess: () => {
                setMessage('Order created successfully!');
                router.visit('/orders');
            },
            onError: () => {
                setMessage('Failed to create order. Check the form for errors.');
            },
        });
    };

    return (
        <>
            <Head title="Create Order as Seller" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Create Order as Seller"
                    description="Create an order for a buyer"
                />

                <form onSubmit={submit} className="space-y-6">
                    {templatesList.length > 0 && (
                        <div className="grid gap-2">
                            <Label htmlFor="template">Load from Template (optional)</Label>
                            <select
                                id="template"
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                onChange={(e) => loadTemplate(e.target.value)}
                                defaultValue=""
                            >
                                <option value="" disabled>Select a template</option>
                                {templatesList.map((t) => (
                                    <option key={t.id} value={t.id}>{t.template_name}</option>
                                ))}
                            </select>
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="buyer_phone">Buyer Phone</Label>
                        <Input
                            id="buyer_phone"
                            value={data.buyer_phone}
                            onChange={(e) => setData('buyer_phone', e.target.value)}
                            required
                            placeholder="712345678"
                        />
                        <InputError message={errors.buyer_phone} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="item_description">Item Description</Label>
                        <textarea
                            id="item_description"
                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            value={data.item_description}
                            onChange={(e) => setData('item_description', e.target.value)}
                            required
                            placeholder="Describe the item being sold"
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
                        {data.price && parseInt(data.price) >= 10 && (
                            <p className="text-xs text-muted-foreground">
                                You will receive: KES {(parseInt(data.price) - 50).toLocaleString()} (after KES 50 flat fee)
                            </p>
                        )}
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
                        <p className={`text-sm ${message.includes('successfully') ? 'text-green-600' : 'text-red-600'}`}>
                            {message}
                        </p>
                    )}

                    <div className="flex items-center gap-4">
                        <Button disabled={processing}>
                            {processing ? 'Creating...' : 'Create Order'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

CreateSellerOrder.layout = {
    breadcrumbs: [
        {
            title: 'Orders',
            href: '/orders',
        },
        {
            title: 'Create as Seller',
            href: '/orders/create/seller',
        },
    ],
};
