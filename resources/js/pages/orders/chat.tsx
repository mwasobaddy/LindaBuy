import { Head, usePage, Link } from '@inertiajs/react';
import { useEffect, useState, useRef, useCallback } from 'react';
import Heading from '@/components/heading';
import ChatBubble from '@/components/chat/ChatBubble';
import ChatInput from '@/components/chat/ChatInput';
import echo from '@/lib/echo';

interface ChatMessage {
    id: number;
    order_id: number;
    sender_id: number;
    sender_type: 'buyer' | 'seller';
    message: string;
    created_at: string;
    sender: { id: number; name: string } | null;
}

interface OrderInfo {
    id: number;
    status: string;
    price: number;
    item_description: string;
    delivery_type: string;
    created_at: string;
    initiator_type: string;
    buyer: { id: number; name: string };
    seller: { id: number; shop_name: string; user_id: number };
}

const formatKes = (cents: number) => (cents / 100).toLocaleString('en-KE', {
    style: 'currency',
    currency: 'KES',
});

export default function OrderChat() {
    const { order, auth } = usePage().props as {
        order: OrderInfo;
        auth: { user?: { id: number; name: string; role?: string; permissions?: string[] } };
    };

    const user = auth?.user;
    const isBuyer = user?.id === order.buyer.id;
    const isSeller = user?.id === order.seller.user_id;

    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const [hasMore, setHasMore] = useState(true);
    const [page, setPage] = useState(1);

    const fetchMessages = useCallback(async (pageNum = 1, append = false) => {
        try {
            const res = await fetch(`/api/orders/${order.id}/messages?page=${pageNum}`, {
                headers: { 'X-CSRF-TOKEN': (window as any).csrfToken ?? '' },
            });

            if (!res.ok) {
                setError('Failed to load messages');
                return;
            }

            const data = await res.json();
            const pageData = data.data;
            const newMessages = (pageData.data ?? []).reverse();

            if (append) {
                setMessages((prev) => [...newMessages, ...prev]);
            } else {
                setMessages(newMessages);
            }

            setHasMore(pageData.current_page < pageData.last_page);
        } catch {
            setError('Network error');
        } finally {
            setLoading(false);
        }
    }, [order.id]);

    useEffect(() => {
        fetchMessages();
    }, [fetchMessages]);

    useEffect(() => {
        if (!echo) return;

        const channel = echo.private(`order.${order.id}`);

        channel.listen('.message.sent', (e: any) => {
            setMessages((prev) => [
                ...prev,
                {
                    id: e.id,
                    order_id: e.order_id,
                    sender_id: e.sender_id,
                    sender_type: e.sender_type,
                    message: e.message,
                    created_at: e.created_at,
                    sender: { id: e.sender_id, name: e.sender_name },
                },
            ]);
        });

        return () => {
            echo.leave(`order.${order.id}`);
        };
    }, [order.id]);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const loadMore = () => {
        if (!hasMore || loading) return;
        const nextPage = page + 1;
        setPage(nextPage);
        fetchMessages(nextPage, true);
    };

    const handleSend = async (message: string) => {
        const res = await fetch(`/api/orders/${order.id}/messages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (window as any).csrfToken ?? '',
            },
            body: JSON.stringify({ message }),
        });

        if (!res.ok) {
            const data = await res.json();
            throw new Error(data.errors?.[0]?.message ?? 'Failed to send message');
        }
    };

    const activeStatuses = ['pending_accept', 'accepted', 'funds_locked', 'verified', 'in_transit', 'delivered', 'g4s_pickup_confirmed'];
    const canChat = activeStatuses.includes(order.status);

    return (
        <>
            <Head title={`Chat - Order #${order.id}`} />

            <div className="flex h-[calc(100vh-12rem)] flex-col">
                <div className="flex items-center justify-between border-b border-border pb-4">
                    <Heading
                        variant="small"
                        title={`Order #${order.id} Chat`}
                        description={order.item_description}
                    />
                    <div className="flex items-center gap-3 text-sm text-muted-foreground">
                        <span>{formatKes(order.price)}</span>
                        <span className="capitalize">{order.status.replace(/_/g, ' ')}</span>
                        <Link
                            href={`/orders/${order.id}`}
                            className="text-primary hover:underline"
                        >
                            Order Details
                        </Link>
                    </div>
                </div>

                <div className="flex flex-1 flex-col overflow-hidden">
                    <div className="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                        {loading && (
                            <p className="text-center text-sm text-muted-foreground">Loading messages...</p>
                        )}

                        {!loading && messages.length === 0 && (
                            <p className="text-center text-sm text-muted-foreground">
                                No messages yet. Start the conversation!
                            </p>
                        )}

                        {hasMore && !loading && (
                            <button
                                onClick={loadMore}
                                className="w-full text-center text-xs text-muted-foreground hover:text-primary py-2"
                            >
                                Load older messages
                            </button>
                        )}

                        {messages.map((msg) => (
                            <ChatBubble
                                key={msg.id}
                                message={msg.message}
                                senderName={msg.sender?.name ?? 'Unknown'}
                                senderType={msg.sender_type}
                                createdAt={msg.created_at}
                                isOwn={msg.sender_id === user?.id}
                            />
                        ))}

                        <div ref={messagesEndRef} />
                    </div>

                    {canChat ? (
                        <ChatInput onSend={handleSend} />
                    ) : (
                        <div className="border-t border-border bg-muted/50 px-4 py-3 text-center text-sm text-muted-foreground">
                            Chat is closed for this order.
                        </div>
                    )}
                </div>

                {error && (
                    <p className="px-4 pb-2 text-sm text-red-600">{error}</p>
                )}
            </div>
        </>
    );
}

OrderChat.layout = {
    breadcrumbs: [
        {
            title: 'Orders',
            href: '/orders',
        },
        {
            title: 'Chat',
            href: null,
        },
    ],
};
