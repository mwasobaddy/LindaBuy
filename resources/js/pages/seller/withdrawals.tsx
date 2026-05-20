import { Head, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Withdrawal {
    id: number;
    amount: number;
    status: string;
    requested_at: string;
    processed_at: string | null;
    failure_reason: string | null;
}

export default function SellerWithdrawals() {
    const { receivable_balance, withdrawals } = usePage().props as {
        receivable_balance: number;
        withdrawals: Withdrawal[];
    };

    const [amountKes, setAmountKes] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');

    const formatKes = (cents: number) => (cents / 100).toLocaleString('en-KE', {
        style: 'currency',
        currency: 'KES',
    });

    const handleRequest = async (e: React.FormEvent) => {
        e.preventDefault();
        const amountCents = parseInt(amountKes, 10) * 100;
        if (isNaN(amountCents) || amountCents < 1) return;

        setProcessing(true);
        setError('');

        try {
            const res = await fetch('/api/withdrawals/request', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (window as any).csrfToken ?? '' },
                body: JSON.stringify({ amount_cents: amountCents }),
            });

            const data = await res.json();
            if (!res.ok) {
                setError(data.errors?.[0]?.message || 'Request failed');
            } else {
                setAmountKes('');
                router.reload({ only: ['receivable_balance', 'withdrawals'] });
            }
        } catch {
            setError('Failed to submit withdrawal request');
        } finally {
            setProcessing(false);
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
                    description="Request payouts from your seller balance"
                />

                <div className="rounded-xl border border-sidebar-border/70 p-6">
                    <p className="text-sm text-muted-foreground">Receivable Balance</p>
                    <p className="text-3xl font-bold">
                        {receivable_balance !== undefined ? formatKes(receivable_balance) : 'KES 0.00'}
                    </p>

                    <form onSubmit={handleRequest} className="mt-6 space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="amount">Amount (KES)</Label>
                            <Input
                                id="amount"
                                type="number"
                                min="1"
                                value={amountKes}
                                onChange={(e) => setAmountKes(e.target.value)}
                                placeholder="Enter amount"
                            />
                        </div>
                        {error && <p className="text-sm text-red-500">{error}</p>}
                        <Button
                            type="submit"
                            disabled={processing || !amountKes || parseInt(amountKes) < 1}
                        >
                            {processing ? 'Submitting...' : 'Request Withdrawal'}
                        </Button>
                    </form>
                </div>

                <div>
                    <h3 className="mb-4 text-lg font-semibold">Withdrawal History</h3>
                    {(!withdrawals || withdrawals.length === 0) ? (
                        <p className="text-sm text-muted-foreground">No withdrawal requests yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/70">
                                        <th className="px-4 py-3 text-left font-medium">Date</th>
                                        <th className="px-4 py-3 text-right font-medium">Amount</th>
                                        <th className="px-4 py-3 text-center font-medium">Status</th>
                                        <th className="px-4 py-3 text-left font-medium">Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {withdrawals.map((w) => (
                                        <tr key={w.id} className="border-b border-sidebar-border/70 last:border-0">
                                            <td className="px-4 py-3">
                                                {new Date(w.requested_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-4 py-3 text-right">{formatKes(w.amount)}</td>
                                            <td className="px-4 py-3 text-center">{statusBadge(w.status)}</td>
                                            <td className="px-4 py-3">{w.failure_reason || '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

SellerWithdrawals.layout = {
    breadcrumbs: [
        {
            title: 'Withdrawals',
            href: '/seller/withdrawals',
        },
    ],
};
