import { Head, usePage, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useState } from 'react';

interface FailedReversalOrder {
    id: number;
    buyer: { name: string } | null;
    seller: { shop_name: string } | null;
    price: number;
    reversal_failure_reason: string | null;
    reversal_failed_at: string;
    reversal_attempts: number;
    reversal_retry_at: string | null;
    mpesa_transaction_id: string | null;
}

export default function FailedReversals() {
    const { orders } = usePage().props as { orders: FailedReversalOrder[] };
    const [actionMsg, setActionMsg] = useState<string | null>(null);
    const [mpesaIdInput, setMpesaIdInput] = useState<Record<number, string>>({});
    const [resolveDialog, setResolveDialog] = useState<{ id: number; type: string } | null>(null);

    const formatKes = (cents: number) => (cents / 100).toLocaleString('en-KE', {
        style: 'currency',
        currency: 'KES',
    });

    const handleRetry = async (id: number) => {
        const mpesaId = mpesaIdInput[id];
        const body: Record<string, string> = {};
        if (mpesaId) {
            body.mpesa_transaction_id = mpesaId;
        }

        try {
            const res = await fetch(`/api/admin/orders/${id}/retry-reversal`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (window as any).csrfToken ?? '',
                },
                body: JSON.stringify(body),
            });

            const data = await res.json();

            if (res.ok) {
                setActionMsg('Reversal retry dispatched successfully.');
                router.reload({ only: ['orders'] });
            } else {
                setActionMsg(data?.errors?.message || data?.message || 'Retry failed.');
            }
        } catch {
            setActionMsg('Network error.');
        }
    };

    const handleResolve = async (id: number, resolutionType: string) => {
        try {
            const res = await fetch(`/api/admin/orders/${id}/resolve-reversal`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (window as any).csrfToken ?? '',
                },
                body: JSON.stringify({ resolution_type: resolutionType }),
            });

            const data = await res.json();

            if (res.ok) {
                const label = resolutionType === 'force_release' ? 'Force-released' : 'Written off';
                setActionMsg(`${label} successfully.`);
                router.reload({ only: ['orders'] });
            } else {
                setActionMsg(data?.errors?.message || data?.message || 'Action failed.');
            }
        } catch {
            setActionMsg('Network error.');
        } finally {
            setResolveDialog(null);
        }
    };

    return (
        <>
            <Head title="Failed Reversals" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Failed Reversals"
                    description="Orders where the M-Pesa reversal has failed and needs admin attention"
                />

                {actionMsg && (
                    <div className="rounded-lg border border-sidebar-border/70 bg-sidebar-accent px-4 py-2 text-sm">
                        {actionMsg}
                        <button
                            className="ml-2 text-xs underline"
                            onClick={() => setActionMsg(null)}
                        >
                            Dismiss
                        </button>
                    </div>
                )}

                {orders.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No failed reversals found.</p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="px-4 py-3 text-left font-medium">ID</th>
                                    <th className="px-4 py-3 text-left font-medium">Buyer</th>
                                    <th className="px-4 py-3 text-left font-medium">Shop</th>
                                    <th className="px-4 py-3 text-right font-medium">Amount</th>
                                    <th className="px-4 py-3 text-left font-medium">Failure Reason</th>
                                    <th className="px-4 py-3 text-left font-medium">Failed At</th>
                                    <th className="px-4 py-3 text-center font-medium">Attempts</th>
                                    <th className="px-4 py-3 text-left font-medium">Retry At</th>
                                    <th className="px-4 py-3 text-left font-medium">M-Pesa Tx ID</th>
                                    <th className="px-4 py-3 text-center font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.map((o) => (
                                    <tr key={o.id} className="border-b border-sidebar-border/70 last:border-0">
                                        <td className="px-4 py-3">{o.id}</td>
                                        <td className="px-4 py-3">{o.buyer?.name ?? 'N/A'}</td>
                                        <td className="px-4 py-3">{o.seller?.shop_name ?? 'N/A'}</td>
                                        <td className="px-4 py-3 text-right">{formatKes(o.price)}</td>
                                        <td className="max-w-xs truncate px-4 py-3">
                                            {o.reversal_failure_reason ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {new Date(o.reversal_failed_at).toLocaleDateString()}
                                        </td>
                                        <td className="px-4 py-3 text-center">{o.reversal_attempts}</td>
                                        <td className="px-4 py-3">
                                            {o.reversal_retry_at
                                                ? new Date(o.reversal_retry_at).toLocaleDateString()
                                                : '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {o.mpesa_transaction_id ? (
                                                <span className="font-mono text-xs">{o.mpesa_transaction_id}</span>
                                            ) : (
                                                <span className="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                    Missing
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-col gap-1">
                                                <div className="flex items-center gap-1">
                                                    <Input
                                                        className="h-7 w-28 text-xs"
                                                        placeholder="M-Pesa Tx ID"
                                                        value={mpesaIdInput[o.id] ?? ''}
                                                        onChange={(e) =>
                                                            setMpesaIdInput((prev) => ({
                                                                ...prev,
                                                                [o.id]: e.target.value,
                                                            }))
                                                        }
                                                    />
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={
                                                            !o.mpesa_transaction_id &&
                                                            !mpesaIdInput[o.id]
                                                        }
                                                        onClick={() => handleRetry(o.id)}
                                                    >
                                                        Retry
                                                    </Button>
                                                </div>
                                                <div className="flex gap-1">
                                                    <Dialog
                                                        open={resolveDialog?.id === o.id && resolveDialog?.type === 'force_release'}
                                                        onOpenChange={(open) =>
                                                            setResolveDialog(open ? { id: o.id, type: 'force_release' } : null)
                                                        }
                                                    >
                                                        <DialogTrigger asChild>
                                                            <Button size="sm" variant="default">
                                                                Force Release
                                                            </Button>
                                                        </DialogTrigger>
                                                        <DialogContent>
                                                            <DialogHeader>
                                                                <DialogTitle>Force Release Funds</DialogTitle>
                                                                <DialogDescription>
                                                                    This will release escrowed funds to the seller despite the failed
                                                                    reversal. The buyer will need to be compensated out-of-band.
                                                                </DialogDescription>
                                                            </DialogHeader>
                                                            <div className="flex gap-2">
                                                                <Button
                                                                    variant="outline"
                                                                    className="flex-1"
                                                                    onClick={() => setResolveDialog(null)}
                                                                >
                                                                    Cancel
                                                                </Button>
                                                                <Button
                                                                    className="flex-1"
                                                                    onClick={() => handleResolve(o.id, 'force_release')}
                                                                >
                                                                    Confirm Force Release
                                                                </Button>
                                                            </div>
                                                        </DialogContent>
                                                    </Dialog>
                                                    <Dialog
                                                        open={resolveDialog?.id === o.id && resolveDialog?.type === 'write_off'}
                                                        onOpenChange={(open) =>
                                                            setResolveDialog(open ? { id: o.id, type: 'write_off' } : null)
                                                        }
                                                    >
                                                        <DialogTrigger asChild>
                                                            <Button size="sm" variant="destructive">
                                                                Write Off
                                                            </Button>
                                                        </DialogTrigger>
                                                        <DialogContent>
                                                            <DialogHeader>
                                                                <DialogTitle>Write Off as Loss</DialogTitle>
                                                                <DialogDescription>
                                                                    This will record the amount as a platform loss in the REVERSAL_LOSS
                                                                    account. The money will not be returned to the buyer.
                                                                </DialogDescription>
                                                            </DialogHeader>
                                                            <div className="flex gap-2">
                                                                <Button
                                                                    variant="outline"
                                                                    className="flex-1"
                                                                    onClick={() => setResolveDialog(null)}
                                                                >
                                                                    Cancel
                                                                </Button>
                                                                <Button
                                                                    className="flex-1"
                                                                    variant="destructive"
                                                                    onClick={() => handleResolve(o.id, 'write_off')}
                                                                >
                                                                    Confirm Write Off
                                                                </Button>
                                                            </div>
                                                        </DialogContent>
                                                    </Dialog>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

FailedReversals.layout = {
    breadcrumbs: [
        {
            title: 'Failed Reversals',
            href: '/admin/failed-reversals',
        },
    ],
};
