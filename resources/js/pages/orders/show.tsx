import { Head, usePage, router, Link } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Order {
    id: number;
    status: string;
    price: number;
    flat_fee: number;
    item_description: string;
    delivery_type: string;
    delivery_location: string | null;
    initiator_type: string;
    buyer_accepted_at: string | null;
    seller_accepted_at: string | null;
    expiry_at: string | null;
    created_at: string;
    carrier_name: string | null;
    carrier_phone: string | null;
    g4s_branch: string | null;
    g4s_tracking_ref: string | null;
    release_confirmation_token: string | null;
    buyer: { id: number; name: string; phone: string };
    seller: { id: number; shop_name: string };
    agent: { id: number; user: { name: string } } | null;
    issueReports?: unknown[];
    chatMessages?: { id: number; sender_type: string; message: string; created_at: string }[];
}

const statusLabels: Record<string, string> = {
    pending_accept: 'Pending Acceptance',
    accepted: 'Accepted',
    funds_locked: 'Funds Locked',
    verified: 'Verified',
    in_transit: 'In Transit',
    delivered: 'Delivered',
    released: 'Released',
    cancelled: 'Cancelled',
    expired: 'Expired',
    payment_failed: 'Payment Failed',
    g4s_pickup_confirmed: 'G4S Pickup Confirmed',
};

const formatKes = (cents: number) => (cents / 100).toLocaleString('en-KE', {
    style: 'currency',
    currency: 'KES',
});

export default function OrderShow() {
    const { order, auth } = usePage().props as {
        order: Order;
        auth: { user?: { id: number; role?: string; permissions?: string[] } };
    };

    const user = auth?.user;
    const isBuyer = user?.id === order.buyer.id;
    const isSeller = user?.id === (order.seller as any).id;

    const [statusMessage, setStatusMessage] = useState('');
    const [token, setToken] = useState('');
    const [declineReason, setDeclineReason] = useState('');
    const [showDeclineInput, setShowDeclineInput] = useState(false);
    const [showReleaseInput, setShowReleaseInput] = useState(false);

    const submitAction = async (action: string, body?: Record<string, string>) => {
        setStatusMessage('');

        try {
            const res = await fetch(`/api/orders/${order.id}/${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (window as any).csrfToken ?? '' },
                body: body ? JSON.stringify(body) : undefined,
            });

            const data = await res.json();

            if (res.ok) {
                setStatusMessage('Action completed successfully!');
                router.reload({ only: ['order'] });
            } else {
                setStatusMessage(data.errors?.[0]?.message ?? 'Action failed');
            }
        } catch {
            setStatusMessage('Network error');
        }
    };

    const statusActions = () => {
        const actions: { label: string; action: string; body?: Record<string, string> }[] = [];

        if (order.status === 'pending_accept' && order.initiator_type === 'seller' && isBuyer) {
            actions.push({ label: 'Accept Order', action: 'accept' });
            actions.push({ label: 'Decline', action: '_decline' });
        }

        if (order.status === 'funds_locked' && order.initiator_type === 'buyer' && isSeller) {
            actions.push({ label: 'Accept Order', action: 'accept' });
            actions.push({ label: 'Decline', action: '_decline' });
        }

        if (order.status === 'in_transit' && isBuyer) {
            actions.push({ label: 'Confirm Delivery', action: 'confirm-delivery' });
        }

        if (order.status === 'delivered' && isBuyer) {
            actions.push({ label: 'Request Release', action: 'request-release-confirmation' });
            if (showReleaseInput) {
                actions.push({ label: 'Release Payment', action: 'release', body: { confirmation_token: token } });
            }
        }

        return actions;
    };

    const actions = statusActions();

    return (
        <>
            <Head title={`Order #${order.id}`} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={`Order #${order.id}`}
                    description={item_description}
                />

                <div className="rounded-xl border border-sidebar-border/70 p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <p className="text-sm text-muted-foreground">Status</p>
                            <p className="text-lg font-semibold capitalize">
                                {statusLabels[order.status] ?? order.status.replace(/_/g, ' ')}
                            </p>
                        </div>
                        <p className="text-2xl font-bold">{formatKes(order.price)}</p>
                    </div>

                    <dl className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt className="text-muted-foreground">Buyer</dt>
                            <dd>{order.buyer.name} ({order.buyer.phone})</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Seller</dt>
                            <dd>{order.seller.shop_name}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Delivery Type</dt>
                            <dd className="capitalize">{order.delivery_type.replace(/_/g, ' ')}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Flat Fee</dt>
                            <dd>{formatKes(order.flat_fee)}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Initiator</dt>
                            <dd className="capitalize">{order.initiator_type}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Created</dt>
                            <dd>{new Date(order.created_at).toLocaleDateString()}</dd>
                        </div>
                        {order.delivery_location && (
                            <div className="col-span-2">
                                <dt className="text-muted-foreground">Delivery Location</dt>
                                <dd>{order.delivery_location}</dd>
                            </div>
                        )}
                        {order.carrier_name && (
                            <>
                                <div>
                                    <dt className="text-muted-foreground">Carrier Name</dt>
                                    <dd>{order.carrier_name}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Carrier Phone</dt>
                                    <dd>{order.carrier_phone}</dd>
                                </div>
                            </>
                        )}
                        {order.g4s_branch && (
                            <div>
                                <dt className="text-muted-foreground">G4S Branch</dt>
                                <dd>{order.g4s_branch}</dd>
                            </div>
                        )}
                        {order.g4s_tracking_ref && (
                            <div>
                                <dt className="text-muted-foreground">G4S Tracking Ref</dt>
                                <dd>{order.g4s_tracking_ref}</dd>
                            </div>
                        )}
                    </dl>

                    <div className="mt-6">
                        <p className="text-sm text-muted-foreground">Item Description</p>
                        <p className="mt-1 text-sm">{order.item_description}</p>
                    </div>
                </div>

                {actions.length > 0 && (
                    <div className="flex flex-wrap items-center gap-3">
                        {actions.map((btn) => {
                            if (btn.action === '_decline') {
                                return (
                                    <div key={btn.action} className="flex items-center gap-2">
                                        {showDeclineInput ? (
                                            <>
                                                <Input
                                                    placeholder="Reason for declining"
                                                    value={declineReason}
                                                    onChange={(e) => setDeclineReason(e.target.value)}
                                                    className="w-64"
                                                />
                                                <Button
                                                    variant="destructive"
                                                    onClick={() => {
                                                        if (declineReason.trim()) {
                                                            submitAction('decline', { reason: declineReason });
                                                            setShowDeclineInput(false);
                                                        }
                                                    }}
                                                >
                                                    Confirm Decline
                                                </Button>
                                                <Button variant="ghost" onClick={() => setShowDeclineInput(false)}>
                                                    Cancel
                                                </Button>
                                            </>
                                        ) : (
                                            <Button variant="destructive" onClick={() => setShowDeclineInput(true)}>
                                                {btn.label}
                                            </Button>
                                        )}
                                    </div>
                                );
                            }

                            if (btn.action === 'release') {
                                return (
                                    <div key={btn.action} className="flex items-center gap-2">
                                        <Input
                                            placeholder="6-digit confirmation token"
                                            value={token}
                                            onChange={(e) => setToken(e.target.value)}
                                            className="w-48"
                                            maxLength={6}
                                        />
                                        <Button
                                            onClick={() => {
                                                if (token.length === 6) {
                                                    submitAction('release', { confirmation_token: token });
                                                }
                                            }}
                                        >
                                            {btn.label}
                                        </Button>
                                    </div>
                                );
                            }

                            if (btn.action === 'request-release-confirmation') {
                                return (
                                    <Button
                                        key={btn.action}
                                        onClick={() => {
                                            submitAction('request-release-confirmation');
                                            setShowReleaseInput(true);
                                        }}
                                    >
                                        {btn.label}
                                    </Button>
                                );
                            }

                            return (
                                <Button
                                    key={btn.action}
                                    onClick={() => submitAction(btn.action, btn.body)}
                                >
                                    {btn.label}
                                </Button>
                            );
                        })}
                    </div>
                )}

                {statusMessage && (
                    <p className={`text-sm ${statusMessage.includes('successfully') ? 'text-green-600' : 'text-red-600'}`}>
                        {statusMessage}
                    </p>
                )}

                <div className="rounded-xl border border-sidebar-border/70 p-6">
                    <div className="flex items-center justify-between">
                        <h3 className="text-lg font-semibold">Chat</h3>
                        <Link
                            href={`/orders/${order.id}/chat`}
                            className="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            Open Chat
                        </Link>
                    </div>
                    {order.chatMessages && order.chatMessages.length > 0 && (
                        <p className="mt-2 text-sm text-muted-foreground">
                            {order.chatMessages.length} message{order.chatMessages.length !== 1 ? 's' : ''}
                        </p>
                    )}
                </div>

                <div className="rounded-xl border border-sidebar-border/70 p-6">
                    <h3 className="mb-4 text-lg font-semibold">Timeline</h3>
                    <div className="space-y-2 text-sm">
                        <p><span className="text-muted-foreground">Created:</span> {new Date(order.created_at).toLocaleString()}</p>
                        {order.buyer_accepted_at && (
                            <p><span className="text-muted-foreground">Buyer accepted:</span> {new Date(order.buyer_accepted_at).toLocaleString()}</p>
                        )}
                        {order.seller_accepted_at && (
                            <p><span className="text-muted-foreground">Seller accepted:</span> {new Date(order.seller_accepted_at).toLocaleString()}</p>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

OrderShow.layout = {
    breadcrumbs: [
        {
            title: 'Orders',
            href: '/orders',
        },
    ],
};
