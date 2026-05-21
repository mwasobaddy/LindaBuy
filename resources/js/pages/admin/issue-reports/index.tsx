import { Head, usePage } from '@inertiajs/react';
import { useState, useCallback } from 'react';
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

interface AgentUser {
    id: number;
    name: string;
}

interface OrderInfo {
    id: number;
    item_description: string;
    status: string;
    buyer: { id: number; name: string } | null;
    seller: { user: { id: number; name: string } | null } | null;
}

interface IssueReport {
    id: number;
    order: OrderInfo | null;
    order_id: number;
    agent: { id: number; user: AgentUser | null } | null;
    agent_id: number | null;
    issue_type: string;
    description: string;
    status: string;
    reported_at: string;
    resolved_at: string | null;
    created_at: string;
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
    data: IssueReport[];
    meta?: PaginationMeta;
}

const issueTypeLabels: Record<string, string> = {
    QUANTITY_MISMATCH: 'Quantity Mismatch',
    QUALITY_ISSUE: 'Quality Issue',
    NOT_AS_DESCRIBED: 'Not as Described',
    OTHER: 'Other',
};

const statusColors: Record<string, string> = {
    REPORTED: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    UNDER_REVIEW: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    RESOLVED: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    DISMISSED: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
};

const issueTypeColors: Record<string, string> = {
    QUANTITY_MISMATCH: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    QUALITY_ISSUE: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    NOT_AS_DESCRIBED: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    OTHER: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
};

export default function IssueReports() {
    const { statuses: _statuses, issue_types: _issueTypes } = usePage().props as {
        statuses: string[];
        issue_types: string[];
    };

    const [data, setData] = useState<PaginatedResponse>({ data: [] });
    const [filters, setFilters] = useState({
        status: '',
        issue_type: '',
        order_id: '',
        date_from: '',
        date_to: '',
    });
    const [expandedRow, setExpandedRow] = useState<number | null>(null);
    const [actionMsg, setActionMsg] = useState<string | null>(null);

    const fetchReports = useCallback(
        async (page = 1) => {
            const params = new URLSearchParams();
            params.set('page', String(page));
            if (filters.status) params.set('status', filters.status);
            if (filters.issue_type) params.set('issue_type', filters.issue_type);
            if (filters.order_id) params.set('order_id', filters.order_id);
            if (filters.date_from) params.set('date_from', filters.date_from);
            if (filters.date_to) params.set('date_to', filters.date_to);

            try {
                const res = await fetch(`/api/admin/issue-reports?${params}`, {
                    headers: {
                        'X-CSRF-TOKEN': (window as any).csrfToken ?? '',
                    },
                });
                const json = await res.json();

                if (res.ok) {
                    setData(json.data as PaginatedResponse);
                }
            } catch {
                setActionMsg('Failed to load issue reports.');
            }
        },
        [filters],
    );

    const handleApplyFilters = () => {
        fetchReports(1);
    };

    const handleResolve = async (id: number) => {
        try {
            const res = await fetch(`/api/admin/issue-reports/${id}/resolve`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (window as any).csrfToken ?? '',
                },
            });
            const json = await res.json();

            if (res.ok) {
                setActionMsg('Issue report resolved.');
                fetchReports(data.meta?.current_page ?? 1);
            } else {
                setActionMsg(json?.errors?.message || json?.message || 'Failed to resolve.');
            }
        } catch {
            setActionMsg('Network error.');
        }
    };

    const handleDismiss = async (id: number) => {
        try {
            const res = await fetch(`/api/admin/issue-reports/${id}/dismiss`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (window as any).csrfToken ?? '',
                },
            });
            const json = await res.json();

            if (res.ok) {
                setActionMsg('Issue report dismissed.');
                fetchReports(data.meta?.current_page ?? 1);
            } else {
                setActionMsg(json?.errors?.message || json?.message || 'Failed to dismiss.');
            }
        } catch {
            setActionMsg('Network error.');
        }
    };

    const handlePageChange = (page: number) => {
        fetchReports(page);
    };

    const meta = data.meta;

    return (
        <>
            <Head title="Issue Reports" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Issue Reports"
                    description="View and manage agent-reported order issues"
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
                                        {s}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="w-40">
                        <label className="mb-1 block text-xs font-medium text-muted-foreground">
                            Issue Type
                        </label>
                        <Select
                            value={filters.issue_type}
                            onValueChange={(v) =>
                                setFilters((prev) => ({ ...prev, issue_type: v }))
                            }
                        >
                            <SelectTrigger className="h-9 text-xs">
                                <SelectValue placeholder="All" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                {_issueTypes.map((t) => (
                                    <SelectItem key={t} value={t}>
                                        {issueTypeLabels[t] ?? t}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="w-32">
                        <label className="mb-1 block text-xs font-medium text-muted-foreground">
                            Order ID
                        </label>
                        <Input
                            className="h-9 text-xs"
                            placeholder="Search ID..."
                            value={filters.order_id}
                            onChange={(e) =>
                                setFilters((prev) => ({ ...prev, order_id: e.target.value }))
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
                    <p className="text-sm text-muted-foreground">
                        No issue reports found.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="px-3 py-3 text-left font-medium">ID</th>
                                    <th className="px-3 py-3 text-left font-medium">Order</th>
                                    <th className="px-3 py-3 text-left font-medium">Issue Type</th>
                                    <th className="px-3 py-3 text-left font-medium">Description</th>
                                    <th className="px-3 py-3 text-left font-medium">Agent</th>
                                    <th className="px-3 py-3 text-center font-medium">Status</th>
                                    <th className="px-3 py-3 text-left font-medium">Reported</th>
                                    <th className="px-3 py-3 text-center font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.data.map((report) => (
                                    <tr
                                        key={report.id}
                                        className="cursor-pointer border-b border-sidebar-border/70 last:border-0 hover:bg-sidebar-accent/50"
                                        onClick={() =>
                                            setExpandedRow(
                                                expandedRow === report.id ? null : report.id,
                                            )
                                        }
                                    >
                                        <td className="px-3 py-3">{report.id}</td>
                                        <td className="px-3 py-3">
                                            {report.order ? `#${report.order.id}` : '—'}
                                        </td>
                                        <td className="px-3 py-3">
                                            <span
                                                className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${
                                                    issueTypeColors[report.issue_type] ??
                                                    'bg-gray-100 text-gray-800'
                                                }`}
                                            >
                                                {issueTypeLabels[report.issue_type] ?? report.issue_type}
                                            </span>
                                        </td>
                                        <td className="max-w-48 truncate px-3 py-3">
                                            {report.description}
                                        </td>
                                        <td className="px-3 py-3">
                                            {report.agent?.user?.name ?? '—'}
                                        </td>
                                        <td className="px-3 py-3 text-center">
                                            <span
                                                className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${
                                                    statusColors[report.status] ??
                                                    'bg-gray-100 text-gray-800'
                                                }`}
                                            >
                                                {report.status}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-3 text-xs">
                                            {new Date(report.created_at).toLocaleString()}
                                        </td>
                                        <td className="px-3 py-3 text-center">
                                            {(report.status === 'REPORTED' || report.status === 'UNDER_REVIEW') && (
                                                <div className="flex justify-center gap-1">
                                                    <Button
                                                        size="sm"
                                                        variant="default"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            handleResolve(report.id);
                                                        }}
                                                    >
                                                        Resolve
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            handleDismiss(report.id);
                                                        }}
                                                    >
                                                        Dismiss
                                                    </Button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Expanded row detail */}
                {expandedRow !== null && (
                    <div className="rounded-xl border border-sidebar-border/70 p-4">
                        <h3 className="mb-2 text-sm font-medium">
                            Issue Report #{expandedRow} — Full Description
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            {data.data.find((r) => r.id === expandedRow)?.description ??
                                'No description'}
                        </p>
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

IssueReports.layout = {
    breadcrumbs: [
        {
            title: 'Issue Reports',
            href: '/admin/issue-reports',
        },
    ],
};
