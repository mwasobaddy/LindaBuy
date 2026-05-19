import { Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Agent, User } from '@/types';

interface AgentWithUser extends Agent {
    user: User;
}

export default function AgentReview({ agent }: { agent: AgentWithUser }) {
    const [rejectOpen, setRejectOpen] = useState(false);
    const [rejectionReason, setRejectionReason] = useState('');
    const [actionLoading, setActionLoading] = useState<string | null>(null);

    const performAction = async (url: string, method: string, body?: Record<string, string>) => {
        setActionLoading(url);
        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (window as any).csrfToken ?? '',
                    Accept: 'application/json',
                },
                body: body ? JSON.stringify(body) : undefined,
            });

            if (response.ok) {
                window.location.reload();
            } else {
                const data = await response.json();
                alert(data.errors?.[0]?.message ?? 'Action failed');
            }
        } catch {
            alert('An error occurred');
        } finally {
            setActionLoading(null);
        }
    };

    return (
        <>
            <Head title="Review Agent" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={`Review Agent: ${agent.user.name}`}
                    description={`Phone: ${agent.user.phone}`}
                />

                <div className="rounded-xl border border-sidebar-border/70 p-4">
                    <div className="mt-4 space-y-2 text-sm">
                        <p>
                            <span className="font-medium">Name:</span> {agent.user.name}
                        </p>
                        <p>
                            <span className="font-medium">Phone:</span> {agent.user.phone}
                        </p>
                        <p>
                            <span className="font-medium">Status:</span>{' '}
                            <span
                                className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${
                                    agent.kyc_status === 'APPROVED'
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                        : agent.kyc_status === 'REJECTED'
                                          ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                          : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                }`}
                            >
                                {agent.kyc_status}
                            </span>
                        </p>

                        {agent.kyc_status === 'PENDING' && (
                            <div className="mt-4 flex gap-2">
                                <Button
                                    variant="default"
                                    onClick={() =>
                                        performAction(
                                            `/api/admin/agents/${agent.id}/approve`,
                                            'PATCH'
                                        )
                                    }
                                    disabled={actionLoading !== null}
                                >
                                    {actionLoading === `/api/admin/agents/${agent.id}/approve` ? (
                                        <Spinner />
                                    ) : null}
                                    Approve
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={() => setRejectOpen(!rejectOpen)}
                                >
                                    Reject
                                </Button>
                            </div>
                        )}

                        {agent.rejected_reason && (
                            <p className="mt-2 text-red-600 dark:text-red-400">
                                <span className="font-medium">Reason:</span>{' '}
                                {agent.rejected_reason}
                            </p>
                        )}

                        {rejectOpen && (
                            <div className="mt-4 space-y-2">
                                <Label htmlFor="reject-reason">Rejection Reason</Label>
                                <Input
                                    id="reject-reason"
                                    value={rejectionReason}
                                    onChange={(e) => setRejectionReason(e.target.value)}
                                    placeholder="Reason for rejection"
                                />
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => {
                                        if (rejectionReason.trim()) {
                                            performAction(
                                                `/api/admin/agents/${agent.id}/reject`,
                                                'PATCH',
                                                { rejection_reason: rejectionReason }
                                            );
                                        }
                                    }}
                                    disabled={!rejectionReason.trim() || actionLoading !== null}
                                >
                                    Confirm Rejection
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

AgentReview.layout = {
    breadcrumbs: [
        {
            title: 'Agents',
            href: '/admin/agents/pending',
        },
        {
            title: 'Review',
            href: '#',
        },
    ],
};
