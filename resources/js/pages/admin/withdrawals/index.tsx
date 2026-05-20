import { Head, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

interface WithdrawalUser {
    name: string;
    phone: string;
}

interface WithdrawalSeller {
    user: WithdrawalUser;
    shop_name: string;
}

interface Withdrawal {
    id: number;
    seller_id: number;
    seller: WithdrawalSeller;
    amount: number;
    status: string;
    requested_at: string;
    processed_at: string | null;
    failure_reason: string | null;
}

export default function AdminWithdrawals() {
    const { withdrawals } = usePage().props as { withdrawals: Withdrawal[] };
    const [statusFilter, setStatusFilter] = useState('');
    const [failReason, setFailReason] = useState('');
    const [failDialog, setFailDialog] = useState<number | null>(null);

    const formatKes = (cents: number) => (cents / 100).toLocaleString('en-KE', {
        style: 'currency',
        currency: 'KES',
    });

    const filtered = statusFilter
        ? withdrawals.filter((w) => w.status === statusFilter)
        : withdrawals;

    const handleAction = async (id: number, action: string, body?: Record<string, string>) => {
        try {
            const res = await fetch(`/api/admin/withdrawals/${id}/${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (window as any).csrfToken ?? '',
                },
                body: body ? JSON.stringify(body) : undefined,
            });

            if (res.ok) {
                router.reload({ only: ['withdrawals'] });
            }
        } catch {
            // ignore
        }
    };

    const statusBadge = (status: string) => {
        const colors: Record<string, string> = {
            pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        };
        return (
            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${colors[status] || colors.pending}`}>
                {status}
            </span>
        );
    };

    return (
        <>
            <Head title="Withdrawals" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Withdrawals"
                    description="Manage seller withdrawal requests"
                />

                <div className="flex gap-2">
                    {['', 'pending', 'processing', 'completed', 'failed'].map((s) => (
                        <Button
                            key={s}
                            variant={statusFilter === s ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setStatusFilter(s)}
                        >
                            {s || 'All'}
                        </Button>
                    ))}
                </div>

                {filtered.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No withdrawals found.</p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="px-4 py-3 text-left font-medium">ID</th>
                                    <th className="px-4 py-3 text-left font-medium">Seller</th>
                                    <th className="px-4 py-3 text-left font-medium">Shop</th>
                                    <th className="px-4 py-3 text-right font-medium">Amount</th>
                                    <th className="px-4 py-3 text-center font-medium">Status</th>
                                    <th className="px-4 py-3 text-left font-medium">Requested</th>
                                    <th className="px-4 py-3 text-center font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.map((w) => (
                                    <tr key={w.id} className="border-b border-sidebar-border/70 last:border-0">
                                        <td className="px-4 py-3">{w.id}</td>
                                        <td className="px-4 py-3">{w.seller?.user?.name ?? 'N/A'}</td>
                                        <td className="px-4 py-3">{w.seller?.shop_name ?? 'N/A'}</td>
                                        <td className="px-4 py-3 text-right">{formatKes(w.amount)}</td>
                                        <td className="px-4 py-3 text-center">{statusBadge(w.status)}</td>
                                        <td className="px-4 py-3">
                                            {new Date(w.requested_at).toLocaleDateString()}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <div className="flex items-center justify-center gap-1">
                                                {w.status === 'pending' && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleAction(w.id, 'process')}
                                                    >
                                                        Process
                                                    </Button>
                                                )}
                                                {(w.status === 'processing' || w.status === 'pending') && (
                                                    <>
                                                        <Button
                                                            size="sm"
                                                            variant="default"
                                                            onClick={() => handleAction(w.id, 'complete')}
                                                        >
                                                            Complete
                                                        </Button>
                                                        <Dialog
                                                            open={failDialog === w.id}
                                                            onOpenChange={(open) => {
                                                                setFailDialog(open ? w.id : null);
                                                                if (!open) setFailReason('');
                                                            }}
                                                        >
                                                            <DialogTrigger asChild>
                                                                <Button size="sm" variant="destructive">
                                                                    Fail
                                                                </Button>
                                                            </DialogTrigger>
                                                            <DialogContent>
                                                                <DialogHeader>
                                                                    <DialogTitle>Mark as Failed</DialogTitle>
                                                                    <DialogDescription>
                                                                        Provide a reason for the failure.
                                                                    </DialogDescription>
                                                                </DialogHeader>
                                                                <div className="space-y-4">
                                                                    <div className="grid gap-2">
                                                                        <Label htmlFor="reason">Failure Reason</Label>
                                                                        <Input
                                                                            id="reason"
                                                                            value={failReason}
                                                                            onChange={(e) => setFailReason(e.target.value)}
                                                                            placeholder="Reason for failure"
                                                                        />
                                                                    </div>
                                                                    <Button
                                                                        onClick={() => {
                                                                            handleAction(w.id, 'fail', {
                                                                                failure_reason: failReason,
                                                                            });
                                                                            setFailDialog(null);
                                                                            setFailReason('');
                                                                        }}
                                                                        disabled={!failReason}
                                                                        className="w-full"
                                                                        variant="destructive"
                                                                    >
                                                                        Confirm Failure
                                                                    </Button>
                                                                </div>
                                                            </DialogContent>
                                                        </Dialog>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {filtered.some((w) => w.failure_reason) && (
                    <div>
                        <h3 className="mb-2 text-lg font-semibold">Failure Reasons</h3>
                        {filtered
                            .filter((w) => w.failure_reason)
                            .map((w) => (
                                <p key={w.id} className="text-sm text-muted-foreground">
                                    Withdrawal #{w.id}: {w.failure_reason}
                                </p>
                            ))}
                    </div>
                )}
            </div>
        </>
    );
}

AdminWithdrawals.layout = {
    breadcrumbs: [
        {
            title: 'Withdrawals',
            href: '/admin/withdrawals',
        },
    ],
};
