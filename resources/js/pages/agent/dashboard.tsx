import { Head, usePage, router } from '@inertiajs/react';
import { useEffect, useState, useCallback } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import echo from '@/lib/echo';

interface Order {
    id: number;
    status: string;
    price: number;
    item_description: string;
    delivery_type: string;
    delivery_location: string | null;
    created_at: string;
    seller: { id: number; shop_name: string };
    buyer: { id: number; name: string; phone: string };
    carrier_name?: string | null;
    carrier_phone?: string | null;
    g4s_branch?: string | null;
    g4s_tracking_ref?: string | null;
}

const formatKes = (cents: number) => (cents / 100).toLocaleString('en-KE', {
    style: 'currency',
    currency: 'KES',
});

export default function AgentDashboard() {
    const { auth } = usePage().props as {
        auth: { user?: { id: number; role?: string; permissions?: string[] } };
    };

    const [availableJobs, setAvailableJobs] = useState<Order[]>([]);
    const [myJobs, setMyJobs] = useState<Order[]>([]);
    const [loading, setLoading] = useState(true);
    const [statusMessage, setStatusMessage] = useState('');
    const [verifyData, setVerifyData] = useState<Record<number, { carrier_name: string; carrier_phone: string; g4s_branch: string; g4s_tracking_ref: string }>>({});
    const [showVerifyForm, setShowVerifyForm] = useState<Record<number, boolean>>({});
    const [issueData, setIssueData] = useState<Record<number, { issue_type: string; description: string }>>({});
    const [showIssueForm, setShowIssueForm] = useState<Record<number, boolean>>({});

    const fetchJobs = useCallback(async () => {
        try {
            const [availableRes, myJobsRes] = await Promise.all([
                fetch('/api/orders/available-jobs', {
                    headers: { 'X-CSRF-TOKEN': (window as any).csrfToken ?? '' },
                }),
                fetch('/api/orders/my-jobs', {
                    headers: { 'X-CSRF-TOKEN': (window as any).csrfToken ?? '' },
                }),
            ]);

            if (availableRes.ok) {
                const data = await availableRes.json();
                setAvailableJobs(data.data ?? []);
            }

            if (myJobsRes.ok) {
                const data = await myJobsRes.json();
                setMyJobs(data.data ?? []);
            }
        } catch {
            setStatusMessage('Failed to load jobs');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchJobs();
    }, [fetchJobs]);

    useEffect(() => {
        if (!echo) return;

        const channel = echo.private('agents.orders');

        channel.listen('OrderAvailableForVerification', (e: any) => {
            fetchJobs();
        });

        return () => {
            echo.leave('agents.orders');
        };
    }, [fetchJobs]);

    const submitAction = async (action: string, orderId: number, body?: Record<string, string>) => {
        setStatusMessage('');

        try {
            const res = await fetch(`/api/orders/${orderId}/${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (window as any).csrfToken ?? '' },
                body: body ? JSON.stringify(body) : undefined,
            });

            const data = await res.json();

            if (res.ok) {
                setStatusMessage(`${action} completed successfully!`);
                fetchJobs();
            } else {
                setStatusMessage(data.errors?.[0]?.message ?? 'Action failed');
            }
        } catch {
            setStatusMessage('Network error');
        }
    };

    const getVerifyForm = (order: Order) => {
        const v = verifyData[order.id] ?? { carrier_name: '', carrier_phone: '', g4s_branch: '', g4s_tracking_ref: '' };

        if (order.delivery_type === 'shop_delivery') {
            return (
                <div className="flex flex-wrap items-end gap-3 mt-2">
                    <div className="grid gap-1">
                        <Label>Carrier Name</Label>
                        <Input
                            placeholder="Driver's name"
                            value={v.carrier_name}
                            onChange={(e) => setVerifyData((prev) => ({ ...prev, [order.id]: { ...prev[order.id], carrier_name: e.target.value } }))}
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label>Carrier Phone</Label>
                        <Input
                            placeholder="0712345678"
                            value={v.carrier_phone}
                            onChange={(e) => setVerifyData((prev) => ({ ...prev, [order.id]: { ...prev[order.id], carrier_phone: e.target.value } }))}
                        />
                    </div>
                    <Button
                        onClick={() => submitAction('verify', order.id, {
                            carrier_name: v.carrier_name,
                            carrier_phone: v.carrier_phone,
                        })}
                    >
                        Confirm Handover
                    </Button>
                </div>
            );
        }

        return (
            <div className="flex flex-wrap items-end gap-3 mt-2">
                <div className="grid gap-1">
                    <Label>G4S Branch</Label>
                    <Input
                        placeholder="Branch name"
                        value={v.g4s_branch}
                        onChange={(e) => setVerifyData((prev) => ({ ...prev, [order.id]: { ...prev[order.id], g4s_branch: e.target.value } }))}
                    />
                </div>
                <div className="grid gap-1">
                    <Label>Tracking Ref (optional)</Label>
                    <Input
                        placeholder="G4S ref"
                        value={v.g4s_tracking_ref}
                        onChange={(e) => setVerifyData((prev) => ({ ...prev, [order.id]: { ...prev[order.id], g4s_tracking_ref: e.target.value } }))}
                    />
                </div>
                <Button
                    onClick={() => submitAction('verify', order.id, {
                        g4s_branch: v.g4s_branch,
                        g4s_tracking_ref: v.g4s_tracking_ref,
                    })}
                >
                    Confirm Handover
                </Button>
            </div>
        );
    };

    const getIssueForm = (orderId: number) => {
        const issue = issueData[orderId] ?? { issue_type: '', description: '' };

        return (
            <div className="flex flex-wrap items-end gap-3 mt-2">
                <div className="grid gap-1">
                    <Label>Issue Type</Label>
                    <Select
                        value={issue.issue_type}
                        onValueChange={(val) => setIssueData((prev) => ({ ...prev, [orderId]: { ...prev[orderId], issue_type: val } }))}
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Select issue type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="QUANTITY_MISMATCH">Quantity Mismatch</SelectItem>
                            <SelectItem value="QUALITY_ISSUE">Quality Issue</SelectItem>
                            <SelectItem value="NOT_AS_DESCRIBED">Not as Described</SelectItem>
                            <SelectItem value="OTHER">Other</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid gap-1 flex-1 min-w-[200px]">
                    <Label>Description</Label>
                    <Input
                        placeholder="Describe the issue"
                        value={issue.description}
                        onChange={(e) => setIssueData((prev) => ({ ...prev, [orderId]: { ...prev[orderId], description: e.target.value } }))}
                    />
                </div>
                <Button
                    variant="destructive"
                    onClick={() => submitAction('report-issue', orderId, {
                        issue_type: issue.issue_type,
                        description: issue.description,
                    })}
                >
                    Submit Report
                </Button>
            </div>
        );
    };

    const orderCard = (order: Order, showActions: 'accept' | 'verify' | 'none') => (
        <Card key={order.id} className="mb-4">
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="text-base">Order #{order.id}</CardTitle>
                    <Badge variant="outline" className="capitalize">
                        {order.delivery_type.replace(/_/g, ' ')}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <p className="text-sm text-muted-foreground mb-2">{order.item_description}</p>
                <div className="grid grid-cols-2 gap-2 text-sm mb-3">
                    <div>
                        <span className="text-muted-foreground">Price:</span>{' '}
                        <span className="font-semibold">{formatKes(order.price)}</span>
                    </div>
                    <div>
                        <span className="text-muted-foreground">Seller:</span>{' '}
                        <span>{order.seller.shop_name}</span>
                    </div>
                    <div>
                        <span className="text-muted-foreground">Buyer:</span>{' '}
                        <span>{order.buyer.name}</span>
                    </div>
                    <div>
                        <span className="text-muted-foreground">Location:</span>{' '}
                        <span>{order.delivery_location ?? 'N/A'}</span>
                    </div>
                </div>

                {showActions === 'accept' && (
                    <Button onClick={() => submitAction('accept-job', order.id)}>
                        Accept Job
                    </Button>
                )}

                {showActions === 'verify' && (
                    <div className="space-y-2">
                        {showVerifyForm[order.id] ? (
                            getVerifyForm(order)
                        ) : (
                            <div className="flex gap-2">
                                <Button onClick={() => setShowVerifyForm((prev) => ({ ...prev, [order.id]: true }))}>
                                    Verify & Handover
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setShowIssueForm((prev) => ({ ...prev, [order.id]: true }))}
                                >
                                    Report Issue
                                </Button>
                            </div>
                        )}

                        {showIssueForm[order.id] && !showVerifyForm[order.id] && (
                            <div>
                                {getIssueForm(order.id)}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="mt-1"
                                    onClick={() => setShowIssueForm((prev) => ({ ...prev, [order.id]: false }))}
                                >
                                    Cancel
                                </Button>
                            </div>
                        )}
                    </div>
                )}

                {order.carrier_name && (
                    <p className="text-xs text-muted-foreground mt-2">
                        Carrier: {order.carrier_name} ({order.carrier_phone})
                    </p>
                )}
                {order.g4s_branch && (
                    <p className="text-xs text-muted-foreground mt-2">
                        G4S Branch: {order.g4s_branch}
                        {order.g4s_tracking_ref && ` · Ref: ${order.g4s_tracking_ref}`}
                    </p>
                )}
            </CardContent>
        </Card>
    );

    return (
        <>
            <Head title="Agent Dashboard" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Agent Dashboard"
                    description="Available verification jobs and your accepted jobs"
                />

                {loading && <p className="text-muted-foreground">Loading jobs...</p>}

                {!loading && (
                    <>
                        <section>
                            <h2 className="mb-3 text-lg font-semibold">
                                Available Jobs ({availableJobs.length})
                            </h2>
                            {availableJobs.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No orders awaiting verification at the moment.
                                </p>
                            ) : (
                                availableJobs.map((order) => orderCard(order, 'accept'))
                            )}
                        </section>

                        <section>
                            <h2 className="mb-3 text-lg font-semibold">
                                My Jobs ({myJobs.length})
                            </h2>
                            {myJobs.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    You have no active jobs.
                                </p>
                            ) : (
                                myJobs.map((order) =>
                                    order.status === 'funds_locked'
                                        ? orderCard(order, 'verify')
                                        : orderCard(order, 'none')
                                )
                            )}
                        </section>
                    </>
                )}

                {statusMessage && (
                    <p className={`text-sm ${statusMessage.includes('successfully') ? 'text-green-600' : 'text-red-600'}`}>
                        {statusMessage}
                    </p>
                )}
            </div>
        </>
    );
}

AgentDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Agent Dashboard',
            href: '/agent/dashboard',
        },
    ],
};
