import { Head, usePage, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useState, useCallback } from 'react';

interface UserBrief {
    id: number;
    name: string;
    phone: string;
}

interface SellerBrief {
    id: number;
    shop_name: string;
    user: UserBrief | null;
}

interface AgentBrief {
    id: number;
    user: UserBrief | null;
}

interface OrderRecord {
    id: number;
    buyer: UserBrief | null;
    buyer_id: number;
    seller: SellerBrief | null;
    seller_id: number;
    agent: AgentBrief | null;
    agent_id: number | null;
    status: string;
    item_description: string;
    price: number;
    flat_fee: number;
    delivery_type: string;
    delivery_location: string | null;
    created_at: string;
    auto_release_enabled?: boolean;
    mpesa_transaction_id?: string | null;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface PaginatedResponse {
    data: OrderRecord[];
    meta?: PaginationMeta;
}

const statusColors: Record<string, string> = {
    pending_accept: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    accepted: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    funds_locked: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
    verified: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
    in_transit: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    delivered: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    released: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    expired: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
    payment_failed: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    g4s_pickup_confirmed: 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200',
};

const deliveryTypeLabels: Record<string, string> = {
    shop_delivery: 'Shop Delivery',
    g4s: 'G4S Courier',
};

const formatKes = (cents: number) =>
    (cents / 100).toLocaleString('en-KE', {
        style: 'currency',
        currency: 'KES',
    });

export default function AdminOrders() {
    const { statuses: _statuses } = usePage().props as {
        statuses: string[];
    };

    const [data, setData] = useState<PaginatedResponse>({ data: [] });
    const [filters, setFilters] = useState({
        status: '',
        delivery_type: '',
        search: '',
        date_from: '',
        date_to: '',
    });
    const [actionMsg, setActionMsg] = useState<string | null>(null);

    const fetchOrders = useCallback(
        async (page = 1) => {
            const params = new URLSearchParams();
            params.set('page', String(page));
            if (filters.status) params.set('status', filters.status);
            if (filters.delivery_type) params.set('delivery_type', filters.delivery_type);
            if (filters.search) params.set('search', filters.search);
            if (filters.date_from) params.set('date_from', filters.date_from);
            if (filters.date_to) params.set('date_to', filters.date_to);

            try {
                const res = await fetch(`/api/admin/orders?${params}`, {
                    headers: {
                        'X-CSRF-TOKEN': (window as any).csrfToken ?? '',
                    },
                });
                const json = await res.json();

                if (res.ok) {
                    setData(json.data as PaginatedResponse);
                }
            } catch {
                setActionMsg('Failed to load orders.');
            }
        },
        [filters],
    );

    const handleApplyFilters = () => fetchOrders(1);

    const handleQuickAction = async (url: string, method: string, body?: Record<string, unknown>) => {
        try {
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (window as any).csrfToken ?? '',
                },
                body: body ? JSON.stringify(body) : undefined,
            });
            const json = await res.json();

            if (res.ok) {
                setActionMsg(json?.message ?? 'Action completed.');
                fetchOrders(data.meta?.current_page ?? 1);
            } else {
                setActionMsg(json?.message ?? 'Action failed.');
            }
        } catch {
            setActionMsg('Network error.');
        }
    };

    const handlePageChange = (page: number) => fetchOrders(page);

    const meta = data.meta;

    return (
        <>
            <Head title="Orders" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Orders"
                    description="View and manage all platform orders"
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

                {/* Filter bar */}
                <div className="flex flex-wrap items-end gap-3">
                    <div className="w-36">
                        <label className="mb-1 block text-xs font-medium text-muted-foreground">
                            Status
                        </label>
                        <Select
                            value={filters.status}
                            onValueChange={(v) =>
                                setFilters((prev) => ({ ...prev, status: v }))
                            }
                        >
                            <SelectTrigger className="h-9 text-xs">
                                <SelectValue placeholder="All" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                {_statuses.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {s.replace(/_/g, ' ')}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="w-36">
                        <label className="mb-1 block text-xs font-medium text-muted-foreground">
                            Delivery
                        </label>
                        <Select
                            value={filters.delivery_type}
                            onValueChange={(v) =>
                                setFilters((prev) => ({ ...prev, delivery_type: v }))
                            }
                        >
                            <SelectTrigger className="h-9 text-xs">
                                <SelectValue placeholder="All" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem value="shop_delivery">Shop Delivery</SelectItem>
                                <SelectItem value="g4s">G4S Courier</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="w-44">
                        <label className="mb-1 block text-xs font-medium text-muted-foreground">
                            Search
                        </label>
                        <Input
                            className="h-9 text-xs"
                            placeholder="Order ID, item, buyer, seller..."
                            value={filters.search}
                            onChange={(e) =>
                                setFilters((prev) => ({ ...prev, search: e.target.value }))
                            }
                        />
                    </div>
                    <div className="w-36">
                        <label className="mb-1 block text-xs font-medium text-muted-foreground">
                            From
                        </label>
                        <Input
                            className="h-9 text-xs"
                            type="date"
                            value={filters.date_from}
                            onChange={(e) =>
                                setFilters((prev) => ({
                                    ...prev,
                                    date_from: e.target.value,
                                }))
                            }
                        />
                    </div>
                    <div className="w-36">
                        <label className="mb-1 block text-xs font-medium text-muted-foreground">
                            To
                        </label>
                        <Input
                            className="h-9 text-xs"
                            type="date"
                            value={filters.date_to}
                            onChange={(e) =>
                                setFilters((prev) => ({
                                    ...prev,
                                    date_to: e.target.value,
                                }))
                            }
                        />
                    </div>
                    <Button size="sm" onClick={handleApplyFilters}>
                        Apply Filters
                    </Button>
                </div>

                {/* Data table */}
                {data.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No orders found.</p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="px-3 py-3 text-left font-medium">ID</th>
                                    <th className="px-3 py-3 text-left font-medium">Item</th>
                                    <th className="px-3 py-3 text-right font-medium">Price</th>
                                    <th className="px-3 py-3 text-center font-medium">Status</th>
                                    <th className="px-3 py-3 text-left font-medium">Delivery</th>
                                    <th className="px-3 py-3 text-left font-medium">Buyer</th>
                                    <th className="px-3 py-3 text-left font-medium">Seller</th>
                                    <th className="px-3 py-3 text-left font-medium">Agent</th>
                                    <th className="px-3 py-3 text-left font-medium">Created</th>
                                    <th className="px-3 py-3 text-center font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.data.map((order) => (
                                    <tr
                                        key={order.id}
                                        className="border-b border-sidebar-border/70 last:border-0 hover:bg-sidebar-accent/50"
                                    >
                                        <td className="px-3 py-3">#{order.id}</td>
                                        <td className="max-w-40 truncate px-3 py-3">
                                            {order.item_description}
                                        </td>
                                        <td className="px-3 py-3 text-right font-mono text-xs">
                                            {formatKes(order.price)}
                                        </td>
                                        <td className="px-3 py-3 text-center">
                                            <span
                                                className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${
                                                    statusColors[order.status] ??
                                                    'bg-gray-100 text-gray-800'
                                                }`}
                                            >
                                                {order.status.replace(/_/g, ' ')}
                                            </span>
                                        </td>
                                        <td className="px-3 py-3 text-xs">
                                            {deliveryTypeLabels[order.delivery_type] ??
                                                order.delivery_type}
                                        </td>
                                        <td className="px-3 py-3">{order.buyer?.name ?? '—'}</td>
                                        <td className="px-3 py-3">
                                            {order.seller?.shop_name ?? '—'}
                                        </td>
                                        <td className="px-3 py-3 text-xs">
                                            {order.agent?.user?.name ?? '—'}
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-3 text-xs">
                                            {new Date(order.created_at).toLocaleDateString()}
                                        </td>
                                        <td className="px-3 py-3 text-center">
                                            <div className="flex justify-center gap-1">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link href={`/orders/${order.id}`}>
                                                        View
                                                    </Link>
                                                </Button>
                                                {order.status === 'verified' &&
                                                    order.delivery_type === 'g4s' && (
                                                        <Button
                                                            size="sm"
                                                            variant="default"
                                                            onClick={() =>
                                                                handleQuickAction(
                                                                    `/api/admin/orders/${order.id}/confirm-g4s-pickup`,
                                                                    'PATCH',
                                                                )
                                                            }
                                                        >
                                                            Confirm Pickup
                                                        </Button>
                                                    )}
                                                {order.status === 'g4s_pickup_confirmed' && (
                                                    <Button
                                                        size="sm"
                                                        variant="default"
                                                        onClick={() =>
                                                            handleQuickAction(
                                                                `/api/admin/orders/${order.id}/resolve-reversal?resolution_type=force_release`,
                                                                'POST',
                                                                {
                                                                    resolution_type:
                                                                        'force_release',
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Release
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Pagination */}
                {meta && meta.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Showing {meta.from}–{meta.to} of {meta.total}
                        </span>
                        <div className="flex gap-1">
                            {Array.from({ length: meta.last_page }, (_, i) => i + 1).map(
                                (page) => (
                                    <Button
                                        key={page}
                                        size="sm"
                                        variant={
                                            page === meta.current_page
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() => handlePageChange(page)}
                                        className="h-8 w-8 p-0 text-xs"
                                    >
                                        {page}
                                    </Button>
                                ),
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

AdminOrders.layout = {
    breadcrumbs: [
        {
            title: 'Orders',
            href: '/admin/orders',
        },
    ],
};
