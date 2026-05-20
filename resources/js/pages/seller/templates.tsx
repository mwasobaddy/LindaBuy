import { Head, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface OrderTemplate {
    id: number;
    template_name: string;
    item_description: string;
    price: number;
    delivery_type: string;
    delivery_location: string | null;
    created_at: string;
}

const formatKes = (cents: number) => (cents / 100).toLocaleString('en-KE', {
    style: 'currency',
    currency: 'KES',
});

export default function SellerTemplates() {
    const { templates } = usePage().props as { templates: OrderTemplate[] };
    const templatesList = templates ?? [];

    const [showCreate, setShowCreate] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const [formData, setFormData] = useState({
        template_name: '',
        item_description: '',
        price: '',
        delivery_type: 'shop_delivery',
        delivery_location: '',
    });

    const [message, setMessage] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});

    const resetForm = () => {
        setFormData({
            template_name: '',
            item_description: '',
            price: '',
            delivery_type: 'shop_delivery',
            delivery_location: '',
        });
        setErrors({});
    };

    const startEdit = (tmpl: OrderTemplate) => {
        setEditingId(tmpl.id);
        setFormData({
            template_name: tmpl.template_name,
            item_description: tmpl.item_description,
            price: String(tmpl.price),
            delivery_type: tmpl.delivery_type,
            delivery_location: tmpl.delivery_location ?? '',
        });
        setShowCreate(true);
    };

    const submit = async () => {
        setMessage('');
        setErrors({});

        const isEdit = editingId !== null;
        const url = isEdit ? `/api/order-templates/${editingId}` : '/api/order-templates';
        const method = isEdit ? 'PATCH' : 'POST';

        try {
            const res = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (window as any).csrfToken ?? '' },
                body: JSON.stringify(formData),
            });

            const data = await res.json();

            if (res.ok) {
                setMessage(isEdit ? 'Template updated!' : 'Template created!');
                resetForm();
                setShowCreate(false);
                setEditingId(null);
                router.reload({ only: ['templates'] });
            } else {
                const fieldErrors = data.errors ?? [];
                const errMap: Record<string, string> = {};
                fieldErrors.forEach((e: { field?: string; message: string }) => {
                    if (e.field) errMap[e.field] = e.message;
                });
                setErrors(errMap);
                if (!Object.keys(errMap).length) {
                    setMessage('Failed to save template.');
                }
            }
        } catch {
            setMessage('Network error');
        }
    };

    const deleteTemplate = async (id: number) => {
        if (!confirm('Delete this template?')) return;

        try {
            const res = await fetch(`/api/order-templates/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': (window as any).csrfToken ?? '' },
            });

            if (res.ok) {
                router.reload({ only: ['templates'] });
            }
        } catch {
            setMessage('Failed to delete template');
        }
    };

    return (
        <>
            <Head title="Order Templates" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Order Templates"
                        description="Create and manage reusable order templates"
                    />
                    <Button onClick={() => { setShowCreate(!showCreate); setEditingId(null); resetForm(); }}>
                        {showCreate ? 'Cancel' : 'New Template'}
                    </Button>
                </div>

                {showCreate && (
                    <div className="rounded-xl border border-sidebar-border/70 p-6">
                        <h3 className="mb-4 text-base font-medium">
                            {editingId ? 'Edit Template' : 'Create Template'}
                        </h3>
                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="template_name">Template Name</Label>
                                <Input
                                    id="template_name"
                                    value={formData.template_name}
                                    onChange={(e) => setFormData({ ...formData, template_name: e.target.value })}
                                    required
                                    placeholder="My Standard Order"
                                />
                                <InputError message={errors.template_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="item_description">Item Description</Label>
                                <textarea
                                    id="item_description"
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    value={formData.item_description}
                                    onChange={(e) => setFormData({ ...formData, item_description: e.target.value })}
                                    required
                                />
                                <InputError message={errors.item_description} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="price">Price (KES)</Label>
                                <Input
                                    id="price"
                                    type="number"
                                    min="10"
                                    value={formData.price}
                                    onChange={(e) => setFormData({ ...formData, price: e.target.value })}
                                    required
                                />
                                <InputError message={errors.price} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="delivery_type">Delivery Type</Label>
                                <select
                                    id="delivery_type"
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    value={formData.delivery_type}
                                    onChange={(e) => setFormData({ ...formData, delivery_type: e.target.value })}
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
                                    value={formData.delivery_location}
                                    onChange={(e) => setFormData({ ...formData, delivery_location: e.target.value })}
                                />
                                <InputError message={errors.delivery_location} />
                            </div>
                            <Button onClick={submit}>
                                {editingId ? 'Update' : 'Create'}
                            </Button>
                            {message && <p className="text-sm text-muted-foreground">{message}</p>}
                        </div>
                    </div>
                )}

                {templatesList.length === 0 && !showCreate ? (
                    <p className="text-sm text-muted-foreground">No templates yet. Create one to get started.</p>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {templatesList.map((tmpl) => (
                            <div key={tmpl.id} className="rounded-xl border border-sidebar-border/70 p-4">
                                <h4 className="mb-1 font-medium">{tmpl.template_name}</h4>
                                <p className="mb-2 text-sm text-muted-foreground line-clamp-2">{tmpl.item_description}</p>
                                <p className="mb-1 text-sm font-semibold">{formatKes(tmpl.price)}</p>
                                <p className="mb-3 text-xs text-muted-foreground capitalize">{tmpl.delivery_type.replace(/_/g, ' ')}</p>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" onClick={() => startEdit(tmpl)}>
                                        Edit
                                    </Button>
                                    <Button variant="destructive" size="sm" onClick={() => deleteTemplate(tmpl.id)}>
                                        Delete
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

SellerTemplates.layout = {
    breadcrumbs: [
        {
            title: 'Templates',
            href: '/seller/templates',
        },
    ],
};
