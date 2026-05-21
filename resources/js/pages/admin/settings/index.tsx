import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useState, useEffect } from 'react';

interface SettingsMap {
    [key: string]: string | number | boolean;
}

interface SettingEntry {
    key: string;
    value: string | number | boolean;
    type: string;
    description: string | null;
}

const labels: Record<string, string> = {
    flat_fee: 'Flat Fee (cents)',
    expiry_minutes: 'Order Expiry (minutes)',
    payment_expiry_minutes: 'Payment Window (minutes)',
};

const descriptions: Record<string, string> = {
    flat_fee: 'Deducted from seller payout per order. Stored in cents (e.g., 5000 = KES 50.00).',
    expiry_minutes: 'How long a pending order waits before automatically expiring.',
    payment_expiry_minutes: 'How long a buyer has to complete payment after initiating an order.',
};

const formatKes = (cents: number) =>
    (cents / 100).toLocaleString('en-KE', { style: 'currency', currency: 'KES' });

export default function Settings() {
    const { settings: initialSettings } = usePage().props as {
        settings: SettingsMap;
    };

    const [settings, setSettings] = useState<SettingsMap>(initialSettings);
    const [dirty, setDirty] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState<Record<string, boolean>>({});
    const [feePreview, setFeePreview] = useState<Record<string, unknown> | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [actionMsg, setActionMsg] = useState<string | null>(null);

    const fetchSettings = async () => {
        try {
            const res = await fetch('/api/admin/settings', {
                headers: { 'X-CSRF-TOKEN': (window as any).csrfToken ?? '' },
            });
            const json = await res.json();
            if (res.ok) {
                setSettings(json.data as SettingsMap);
            }
        } catch {
            setActionMsg('Failed to load settings.');
        }
    };

    const allKeys = Object.keys(initialSettings);

    useEffect(() => {
        setDirty({});
    }, [settings]);

    const handleSave = async (key: string) => {
        setSaving((prev) => ({ ...prev, [key]: true }));
        setActionMsg(null);

        try {
            const value = dirty[key] ?? String(settings[key]);
            const res = await fetch(`/api/admin/settings/${key}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (window as any).csrfToken ?? '',
                },
                body: JSON.stringify({ value }),
            });

            const json = await res.json();

            if (res.ok) {
                setActionMsg(`"${labels[key] ?? key}" updated successfully.`);
                setDirty((prev) => {
                    const next = { ...prev };
                    delete next[key];
                    return next;
                });
                await fetchSettings();
            } else {
                setActionMsg(json?.errors?.message || json?.message || 'Save failed.');
            }
        } catch {
            setActionMsg('Network error.');
        } finally {
            setSaving((prev) => ({ ...prev, [key]: false }));
        }
    };

    const handleFetchPreview = async () => {
        const flatFeeValue = dirty.flat_fee ?? String(settings.flat_fee ?? 5000);
        const amount = Math.floor(parseInt(flatFeeValue, 10) / 100) || 1;

        setPreviewLoading(true);
        setActionMsg(null);

        try {
            const res = await fetch(`/api/admin/fee-preview/${amount}`, {
                headers: { 'X-CSRF-TOKEN': (window as any).csrfToken ?? '' },
            });
            const json = await res.json();

            if (res.ok) {
                setFeePreview(json.data as Record<string, unknown>);
            } else {
                setActionMsg(json?.errors?.message || json?.message || 'Preview failed.');
            }
        } catch {
            setActionMsg('Network error.');
        } finally {
            setPreviewLoading(false);
        }
    };

    useEffect(() => {
        if (!dirty.flat_fee && dirty.flat_fee !== '') return;
        const timer = setTimeout(() => {
            handleFetchPreview();
        }, 600);
        return () => clearTimeout(timer);
    }, [dirty.flat_fee]);

    return (
        <>
            <Head title="System Settings" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="System Settings"
                    description="Manage platform-wide settings. Changes take effect immediately for new orders."
                />

                {actionMsg && (
                    <div className="rounded-lg border border-sidebar-border/70 bg-sidebar-accent px-4 py-2 text-sm">
                        {actionMsg}
                        <button className="ml-2 text-xs underline" onClick={() => setActionMsg(null)}>
                            Dismiss
                        </button>
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {allKeys.map((key) => {
                        const displayValue = dirty[key] ?? String(settings[key] ?? '');
                        const isDirty = key in dirty;
                        const isSaving = saving[key] ?? false;

                        return (
                            <div
                                key={key}
                                className="rounded-xl border border-sidebar-border/70 p-4"
                            >
                                <label className="mb-1 block text-sm font-medium">
                                    {labels[key] ?? key}
                                </label>
                                <p className="mb-3 text-xs text-muted-foreground">
                                    {descriptions[key] ?? ''}
                                </p>
                                <div className="flex items-center gap-2">
                                    <Input
                                        type={settings[key] !== undefined ? 'number' : 'text'}
                                        value={displayValue}
                                        onChange={(e) =>
                                            setDirty((prev) => ({
                                                ...prev,
                                                [key]: e.target.value,
                                            }))
                                        }
                                        className="h-9"
                                    />
                                    <Button
                                        size="sm"
                                        disabled={!isDirty || isSaving}
                                        onClick={() => handleSave(key)}
                                    >
                                        {isSaving ? 'Saving...' : 'Save'}
                                    </Button>
                                </div>
                                {key === 'flat_fee' && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Current: {formatKes(Number(settings[key] ?? 0))}
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </div>

                {feePreview && (
                    <div className="rounded-xl border border-sidebar-border/70 p-4">
                        <Heading
                            variant="small"
                            title="Fee Preview"
                            description="Shows how the current flat fee affects a sample order"
                        />
                        <div className="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <span className="text-xs text-muted-foreground">Order Amount</span>
                                <p className="font-medium">{String(feePreview.orderAmountDisplay ?? feePreview.flatFeeDisplay ?? '')}</p>
                                <p className="text-xs text-muted-foreground">
                                    {String(feePreview.orderAmount ?? '')} KES
                                </p>
                            </div>
                            <div>
                                <span className="text-xs text-muted-foreground">Flat Fee</span>
                                <p className="font-medium">{String(feePreview.flatFeeDisplay ?? '')}</p>
                            </div>
                            <div>
                                <span className="text-xs text-muted-foreground">Seller Receives</span>
                                <p className="font-medium">{String(feePreview.sellerReceivableDisplay ?? '')}</p>
                            </div>
                            <div>
                                <span className="text-xs text-muted-foreground">Split (Owner / Developer)</span>
                                <p className="font-medium">
                                    {String(feePreview.ownerShareDisplay ?? '')} / {String(feePreview.developerShareDisplay ?? '')}
                                </p>
                            </div>
                        </div>
                        <p className="mt-2 text-xs text-muted-foreground">
                            {String(feePreview.displayText ?? '')}
                        </p>
                    </div>
                )}

                {!feePreview && !previewLoading && (
                    <p className="text-xs text-muted-foreground">
                        Edit the flat fee above to see a fee preview.
                    </p>
                )}
            </div>
        </>
    );
}

Settings.layout = {
    breadcrumbs: [
        {
            title: 'Settings',
            href: '/admin/settings',
        },
    ],
};
