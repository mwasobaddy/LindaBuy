import { Head, usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
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

interface Transaction {
    id: number;
    debit_amount: number;
    credit_amount: number;
    balance_after: number;
    created_at: string;
    transaction: {
        transaction_type: string;
        description: string;
    };
}

export default function WalletIndex() {
    const { wallet_balance, recent_transactions } = usePage().props as {
        wallet_balance: { available: number; ledger: number };
        recent_transactions: Transaction[];
    };

    const [showTopUp, setShowTopUp] = useState(false);
    const [amountKes, setAmountKes] = useState('');
    const [processing, setProcessing] = useState(false);
    const [message, setMessage] = useState('');
    const [checkoutId, setCheckoutId] = useState('');

    const formatKes = (cents: number) => (cents / 100).toLocaleString('en-KE', {
        style: 'currency',
        currency: 'KES',
    });

    const handleTopUp = async () => {
        const amountCents = parseInt(amountKes, 10) * 100;
        if (isNaN(amountCents) || amountCents < 1000) return;

        setProcessing(true);
        setMessage('');

        try {
            const res = await fetch('/api/wallet/top-up', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (window as any).csrfToken ?? '' },
                body: JSON.stringify({ amount_cents: amountCents }),
            });

            const data = await res.json();
            if (data.data?.checkout_request_id) {
                setCheckoutId(data.data.checkout_request_id);
                setMessage('Check your phone for M-Pesa PIN');
            }
        } catch {
            setMessage('Failed to initiate top-up');
        } finally {
            setProcessing(false);
        }
    };

    useEffect(() => {
        if (!checkoutId) return;

        const interval = setInterval(async () => {
            try {
                const res = await fetch(`/api/wallet/status/${checkoutId}`);
                const data = await res.json();
                if (data.data?.status === 'completed') {
                    setMessage('Top-up successful!');
                    setCheckoutId('');
                    clearInterval(interval);
                    router.reload({ only: ['wallet_balance', 'recent_transactions'] });
                } else if (data.data?.status === 'failed') {
                    setMessage('Top-up failed. Please try again.');
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
            <Head title="Wallet" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Wallet"
                    description="Manage your wallet balance and top up via M-Pesa"
                />

                <div className="rounded-xl border border-sidebar-border/70 p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-muted-foreground">Available Balance</p>
                            <p className="text-3xl font-bold">
                                {wallet_balance ? formatKes(wallet_balance.available) : 'KES 0.00'}
                            </p>
                        </div>
                        <Dialog open={showTopUp} onOpenChange={setShowTopUp}>
                            <DialogTrigger asChild>
                                <Button>Top Up</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Top Up Wallet</DialogTitle>
                                    <DialogDescription>
                                        Enter amount in KES (minimum 10 KES)
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="amount">Amount (KES)</Label>
                                        <Input
                                            id="amount"
                                            type="number"
                                            min="10"
                                            value={amountKes}
                                            onChange={(e) => setAmountKes(e.target.value)}
                                            placeholder="100"
                                        />
                                    </div>
                                    {message && (
                                        <p className="text-sm text-muted-foreground">{message}</p>
                                    )}
                                    <Button
                                        onClick={handleTopUp}
                                        disabled={processing || !amountKes || parseInt(amountKes) < 10}
                                        className="w-full"
                                    >
                                        {processing ? 'Processing...' : 'Confirm'}
                                    </Button>
                                </div>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                <div>
                    <h3 className="mb-4 text-lg font-semibold">Transaction History</h3>
                    {(!recent_transactions || recent_transactions.length === 0) ? (
                        <p className="text-sm text-muted-foreground">No transactions yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/70">
                                        <th className="px-4 py-3 text-left font-medium">Date</th>
                                        <th className="px-4 py-3 text-left font-medium">Type</th>
                                        <th className="px-4 py-3 text-left font-medium">Description</th>
                                        <th className="px-4 py-3 text-right font-medium">Amount</th>
                                        <th className="px-4 py-3 text-right font-medium">Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recent_transactions.map((entry) => (
                                        <tr key={entry.id} className="border-b border-sidebar-border/70 last:border-0">
                                            <td className="px-4 py-3">
                                                {new Date(entry.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-4 py-3 capitalize">{entry.transaction?.transaction_type}</td>
                                            <td className="px-4 py-3">{entry.transaction?.description}</td>
                                            <td className="px-4 py-3 text-right">
                                                {entry.credit_amount > 0
                                                    ? `+${formatKes(entry.credit_amount)}`
                                                    : `-${formatKes(entry.debit_amount)}`}
                                            </td>
                                            <td className="px-4 py-3 text-right">{formatKes(entry.balance_after)}</td>
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

WalletIndex.layout = {
    breadcrumbs: [
        {
            title: 'Wallet',
            href: '/wallet',
        },
    ],
};
