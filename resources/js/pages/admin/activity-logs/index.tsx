import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { useState, useEffect } from 'react';

interface AuditLogEntry {
    id: number;
    user_id: number | null;
    action: string;
    entity: string | null;
    entity_id: number | null;
    details: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
    user: { id: number; name: string } | null;
}

interface SummaryItem {
    action: string;
    count: number;
}

export default function ActivityLogs() {
    const { initial_summary } = usePage().props as { initial_summary: SummaryItem[] };

    const [logs, setLogs] = useState<AuditLogEntry[]>([]);
    const [summary, setSummary] = useState<SummaryItem[]>(initial_summary ?? []);
    const [loading, setLoading] = useState(false);
    const [meta, setMeta] = useState<{ current_page: number; last_page: number; total: number } | null>(null);

    const [filters, setFilters] = useState({
        action: '',
        entity: '',
        user_id: '',
        date_from: '',
        date_to: '',
    });

    const fetchLogs = async (page = 1) => {
        setLoading(true);
        try {
            const params = new URLSearchParams();
            params.set('per_page', '50');
            params.set('page', String(page));
            Object.entries(filters).forEach(([k, v]) => {
                if (v) params.set(k, v);
            });

            const res = await fetch(`/api/admin/activity-logs?${params}`, {
                headers: { 'X-CSRF-TOKEN': (window as any).csrfToken ?? '' },
            });
            const json = await res.json();
            if (json.data) {
                setLogs(json.data.data ?? []);
                setMeta({
                    current_page: json.data.current_page,
                    last_page: json.data.last_page,
                    total: json.data.total,
                });
            }
        } catch {
            // silent
        } finally {
            setLoading(false);
        }
    };

    const fetchSummary = async () => {
        try {
            const params = new URLSearchParams();
            Object.entries(filters).forEach(([k, v]) => {
                if (v && k !== 'date_from' && k !== 'date_to' && k !== 'user_id') params.set(k, v);
            });

            const res = await fetch(`/api/admin/activity-summary?${params}`, {
                headers: { 'X-CSRF-TOKEN': (window as any).csrfToken ?? '' },
            });
            const json = await res.json();
            if (json.data) {
                setSummary(json.data);
            }
        } catch {
            // silent
        }
    };

    useEffect(() => {
        fetchLogs();
        fetchSummary();
    }, []);

    const handleApplyFilters = () => {
        fetchLogs();
        fetchSummary();
    };

    const formatAction = (action: string) =>
        action
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (c) => c.toUpperCase());

    return (
        <>
            <Head title="Activity Logs" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Activity Logs"
                    description="Audit trail of all meaningful actions in the system"
                />

                {/* Summary cards */}
                {summary.length > 0 && (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                        {summary.slice(0, 12).map((item) => (
                            <div
                                key={item.action}
                                className="rounded-lg border border-sidebar-border/70 bg-sidebar-accent/50 px-3 py-2 text-center"
                            >
                                <div className="text-lg font-bold">{item.count}</div>
                                <div className="truncate text-xs text-muted-foreground">
                                    {formatAction(item.action)}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Filter bar */}
                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Action</label>
                        <Input
                            className="h-8 w-40 text-xs"
                            placeholder="e.g. order.created"
                            value={filters.action}
                            onChange={(e) => setFilters((p) => ({ ...p, action: e.target.value }))}
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Entity</label>
                        <Input
                            className="h-8 w-32 text-xs"
                            placeholder="order, seller..."
                            value={filters.entity}
                            onChange={(e) => setFilters((p) => ({ ...p, entity: e.target.value }))}
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">User ID</label>
                        <Input
                            className="h-8 w-24 text-xs"
                            placeholder="ID"
                            value={filters.user_id}
                            onChange={(e) => setFilters((p) => ({ ...p, user_id: e.target.value }))}
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">From</label>
                        <Input
                            className="h-8 w-36 text-xs"
                            type="date"
                            value={filters.date_from}
                            onChange={(e) => setFilters((p) => ({ ...p, date_from: e.target.value }))}
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">To</label>
                        <Input
                            className="h-8 w-36 text-xs"
                            type="date"
                            value={filters.date_to}
                            onChange={(e) => setFilters((p) => ({ ...p, date_to: e.target.value }))}
                        />
                    </div>
                    <Button size="sm" onClick={handleApplyFilters}>
                        Apply Filters
                    </Button>
                </div>

                {/* Table */}
                {loading ? (
                    <p className="text-sm text-muted-foreground">Loading...</p>
                ) : logs.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No activity logs found.</p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="px-4 py-3 text-left font-medium">Timestamp</th>
                                    <th className="px-4 py-3 text-left font-medium">User</th>
                                    <th className="px-4 py-3 text-left font-medium">Action</th>
                                    <th className="px-4 py-3 text-left font-medium">Entity</th>
                                    <th className="px-4 py-3 text-left font-medium">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.map((log) => (
                                    <tr
                                        key={log.id}
                                        className="border-b border-sidebar-border/70 last:border-0"
                                    >
                                        <td className="whitespace-nowrap px-4 py-3 text-xs">
                                            {new Date(log.created_at).toLocaleString()}
                                        </td>
                                        <td className="px-4 py-3">
                                            {log.user ? log.user.name : (
                                                <span className="text-muted-foreground">System</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">{formatAction(log.action)}</td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            {log.entity}
                                            {log.entity_id != null ? ` #${log.entity_id}` : ''}
                                        </td>
                                        <td className="max-w-xs truncate px-4 py-3 font-mono text-xs">
                                            {log.details ? JSON.stringify(log.details) : '-'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Pagination */}
                {meta && meta.last_page > 1 && (
                    <div className="flex items-center justify-center gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={meta.current_page <= 1}
                            onClick={() => fetchLogs(meta.current_page - 1)}
                        >
                            Previous
                        </Button>
                        <span className="text-xs text-muted-foreground">
                            Page {meta.current_page} of {meta.last_page} ({meta.total} total)
                        </span>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={meta.current_page >= meta.last_page}
                            onClick={() => fetchLogs(meta.current_page + 1)}
                        >
                            Next
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

ActivityLogs.layout = {
    breadcrumbs: [
        {
            title: 'Activity Logs',
            href: '/admin/activity-logs',
        },
    ],
};
