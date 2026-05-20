import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

interface Order {
    id: number;
    status: string;
    price: number;
    delivery_type: string;
    item_description: string;
    created_at: string;
    initiator_type: string;
    buyer: { id: number; name: string };
    seller: { id: number; shop_name: string };
}

const statusColors: Record<string, string> = {
    pending_accept: 'bg-yellow-100 text-yellow-800',
    accepted: 'bg-blue-100 text-blue-800',
    funds_locked: 'bg-indigo-100 text-indigo-800',
    verified: 'bg-purple-100 text-purple-800',
    in_transit: 'bg-cyan-100 text-cyan-800',
    delivered: 'bg-green-100 text-green-800',
    released: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-red-100 text-red-800',
    expired: 'bg-gray-100 text-gray-800',
    payment_failed: 'bg-orange-100 text-orange-800',
    g4s_pickup_confirmed: 'bg-teal-100 text-teal-800',
};

const formatKes = (cents: number) => (cents / 100).toLocaleString('en-KE', {
    style: 'currency',
    currency: 'KES',
});

export default function OrdersIndex() {
    const { orders } = usePage().props as { orders: Order[] };
    const ordersList = orders ?? [];

    return (
        <>
            <Head title="Orders" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Orders"
                        description="View and manage your orders"
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href="/orders/create/seller">As Seller</Link>
                        </Button>
                        <Button asChild>
                            <Link href="/orders/create/buyer">As Buyer</Link>
                        </Button>
                    </div>
                </div>

                {ordersList.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No orders found.</p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="px-4 py-3 text-left font-medium">ID</th>
                                    <th className="px-4 py-3 text-left font-medium">Item</th>
                                    <th className="px-4 py-3 text-left font-medium">Status</th>
                                    <th className="px-4 py-3 text-left font-medium">Price</th>
                                    <th className="px-4 py-3 text-left font-medium">Delivery</th>
                                    <th className="px-4 py-3 text-left font-medium">Date</th>
                                    <th className="px-4 py-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {ordersList.map((order) => (
                                    <tr key={order.id} className="border-b border-sidebar-border/70 last:border-0">
                                        <td className="px-4 py-3">#{order.id}</td>
                                        <td className="max-w-xs truncate px-4 py-3">{order.item_description}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded px-2 py-0.5 text-xs font-medium capitalize ${statusColors[order.status] ?? 'bg-gray-100 text-gray-800'}`}>
                                                {order.status.replace(/_/g, ' ')}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">{formatKes(order.price)}</td>
                                        <td className="px-4 py-3 capitalize">{order.delivery_type.replace(/_/g, ' ')}</td>
                                        <td className="px-4 py-3">{new Date(order.created_at).toLocaleDateString()}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Button asChild variant="ghost" size="sm">
                                                <Link href={`/orders/${order.id}`}>View</Link>
                                            </Button>
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

OrdersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Orders',
            href: '/orders',
        },
    ],
};
