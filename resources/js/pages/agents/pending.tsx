import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import type { Agent, User } from '@/types';

interface PendingAgent {
    id: number;
    kyc_status: string;
    user: User;
    created_at: string;
}

export default function PendingAgents({
    agents,
}: {
    agents: PendingAgent[];
}) {
    return (
        <>
            <Head title="Pending Agents" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Pending Agents"
                    description="Review agent upgrade requests"
                />

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-left">
                                <th className="px-4 py-3 font-medium">User</th>
                                <th className="px-4 py-3 font-medium">Phone</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Submitted</th>
                                <th className="px-4 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {agents.map((agent) => (
                                <tr
                                    key={agent.id}
                                    className="border-b border-sidebar-border/70 last:border-0"
                                >
                                    <td className="px-4 py-3">{agent.user.name}</td>
                                    <td className="px-4 py-3">{agent.user.phone}</td>
                                    <td className="px-4 py-3">
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
                                    </td>
                                    <td className="px-4 py-3">
                                        {new Date(agent.created_at).toLocaleDateString()}
                                    </td>
                                    <td className="px-4 py-3">
                                        <a
                                            href={`/admin/agents/${agent.id}/review`}
                                            className="text-emerald-600 hover:text-emerald-700 dark:text-emerald-500 dark:hover:text-emerald-400"
                                        >
                                            Review
                                        </a>
                                    </td>
                                </tr>
                            ))}
                            {agents.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No pending agent requests.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

PendingAgents.layout = {
    breadcrumbs: [
        {
            title: 'Agents',
            href: '/admin/agents/pending',
        },
        {
            title: 'Pending Agents',
            href: '/admin/agents/pending',
        },
    ],
};
