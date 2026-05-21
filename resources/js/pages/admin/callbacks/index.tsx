import { Head, usePage, router } from '@inertiajs/react';
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

interface CallbackUser {
    id: number;
    name: string;
}

interface CallbackRecord {
    id: number;
    user: CallbackUser | null;
    user_id: number | null;
    correlation_id: string;
    checkout_request_id: string | null;
    amount: number | null;
    phone: string | null;
    reference: string | null;
    status: string;
    result_code: number | null;
    response_description: string | null;
    callback_payload: Record<string, unknown> | null;
    retry_count: number;
    mpesa_receipt: string | null;
    created_at: string;
    processed_at: string | null;
    last_retried_at: string | null;
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
    data: CallbackRecord[];
    meta?: PaginationMeta;
}

interface Summary {
    total: number;
    pending: number;
    success: number;
    failed: number;
    timed_out: number;
}

const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    success: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    timed_out: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
    retried: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
};

const formatKes = (cents: number | null) => {
    if (cents === null || cents === undefined) return '-';
    return (cents / 100).toLocaleString('en-KE', {
        style: 'currency',
        currency: 'KES',
    });
};

export default function CallbackMonitoring() {
    const { callbacks: initialData } = usePage().props as {
        callbacks: PaginatedResponse;
    };

    const [data, setData] = useState<PaginatedResponse>(initialData);
    const [summary, setSummary] = useState<Summary>(() => {
        const records = initialData.data ?? [];
        return {
            total: initialData.meta?.total ?? records.length,
            pending: records.filter((r) => r.status === 'pending').length,
            success: records.filter((r) => r.status === 'success').length,
            failed: records.filter((r) => r.status === 'failed').length,
            timed_out: records.filter((r) => r.status === 'timed_out').length,
        };
    });
    const [filters, setFilters] = useState({
        status: '',
        phone: '',
        date_from: '',
        date_to: '',
    });
    const [expandedRow, setExpandedRow] = useState<number | null>(null);
    const [actionMsg, setActionMsg] = useState<string | null>(null);

    const fetchCallbacks = useCallback(
        async (page = 1) => {
            const params = new URLSearchParams();
            params.set('page', String(page));
            if (filters.status) params.set('status', filters.status);
            if (filters.phone) params.set('phone', filters.phone);
            if (filters.date_from) params.set('date_from', filters.date_from);
            if (filters.date_to) params.set('date_to', filters.date_to);

            try {
                const res = await fetch(`/api/admin/callbacks?${params}`, {
                    headers: {
                        'X-CSRF-TOKEN': (window as any).csrfToken ?? '',
                    },
                });
                const json = await res.json();

                if (res.ok) {
                    const result = json.data as PaginatedResponse;
                    setData(result);

                    const records = result.data ?? [];
                    setSummary({
                        total: result.meta?.total ?? records.length,
                        pending: records.filter((r) => r.status === 'pending').length,
                        success: records.filter((r) => r.status === 'success').length,
                        failed: records.filter((r) => r.status === 'failed').length,
                        timed_out: records.filter((r) => r.status === 'timed_out').length,
                    });
                }
            } catch {
                setActionMsg('Failed to load callbacks.');
            }
        },
        [filters],
    );

    const handleApplyFilters = () => {
        fetchCallbacks(1);
    };

    const handleRetry = async (id: number) => {
        try {
            const res = await fetch(`/api/admin/callbacks/${id}/retry`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (window as any).csrfToken ?? '',
                },
            });

            const json = await res.json();

            if (res.ok) {
                setActionMsg('Callback retried successfully.');
                fetchCallbacks(data.meta?.current_page ?? 1);
            } else {
                setActionMsg(json?.errors?.message || json?.message || 'Retry failed.');
            }
        } catch {
            setActionMsg('Network error.');
        }
    };

    const handlePageChange = (page: number) => {
        fetchCallbacks(page);
    };

    const meta = data.meta;

    return (
        <>
            <Head title="Callback Monitoring" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Callback Monitoring"
                    description="View and manage M-Pesa STK Push callback records"
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

                {/* Summary cards */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-5">
                    <SummaryCard label="Total" value={summary.total} />
                    <SummaryCard
                        label="Pending"
                        value={summary.pending}
                        className="bg-yellow-50 dark:bg-yellow-950"
                    />
                    <SummaryCard
                        label="Success"
                        value={summary.success}
                        className="bg-green-50 dark:bg-green-950"
                    />
                    <SummaryCard
                        label="Failed"
                        value={summary.failed}
                        className="bg-red-50 dark:bg-red-950"
                    />
                    <SummaryCard
                        label="Timed Out"
                        value={summary.timed_out}
                        className="bg-gray-50 dark:bg-gray-800"
                    />
                </div>

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
                                <SelectItem value="pending">Pending</SelectItem>
                                <SelectItem value="success">Success</SelectItem>
                                <SelectItem value="failed">Failed</SelectItem>
                                <SelectItem value="timed_out">Timed Out</SelectItem>
                                <SelectItem value="retried">Retried</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="w-40">
                        <label className="mb-1 block text-xs font-medium text-muted-foreground">
                            Phone
                        </label>
                        <Input
                            className="h-9 text-xs"
                            placeholder="Search phone..."
                            value={filters.phone}
                            onChange={(e) =>
                                setFilters((prev) => ({ ...prev, phone: e.target.value }))
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
                        No callback records found.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="px-3 py-3 text-left font-medium">ID</th>
                                    <th className="px-3 py-3 text-left font-medium">User</th>
                                    <th className="px-3 py-3 text-left font-medium">Phone</th>
                                    <th className="px-3 py-3 text-right font-medium">Amount</th>
                                    <th className="px-3 py-3 text-left font-medium">Reference</th>
                                    <th className="px-3 py-3 text-center font-medium">Status</th>
                                    <th className="px-3 py-3 text-right font-medium">Result</th>
                                    <th className="px-3 py-3 text-left font-medium">Description</th>
                                    <th className="px-3 py-3 text-left font-medium">Created</th>
                                    <th className="px-3 py-3 text-center font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.data.map((record) => (
                                    <tr
                                        key={record.id}
                                        className="cursor-pointer border-b border-sidebar-border/70 last:border-0 hover:bg-sidebar-accent/50"
                                        onClick={() =>
                                            setExpandedRow(
                                                expandedRow === record.id ? null : record.id,
                                            )
                                        }
                                    >
                                        <td className="px-3 py-3">{record.id}</td>
                                        <td className="px-3 py-3">
                                            {record.user?.name ?? '—'}
                                        </td>
                                        <td className="px-3 py-3 font-mono text-xs">
                                            {record.phone ?? '—'}
                                        </td>
                                        <td className="px-3 py-3 text-right">
                                            {formatKes(record.amount)}
                                        </td>
                                        <td className="max-w-32 truncate px-3 py-3 font-mono text-xs">
                                            {record.reference ?? '—'}
                                        </td>
                                        <td className="px-3 py-3 text-center">
                                            <span
                                                className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${
                                                    statusColors[record.status] ??
                                                    'bg-gray-100 text-gray-800'
                                                }`}
                                            >
                                                {record.status}
                                            </span>
                                        </td>
                                        <td className="px-3 py-3 text-right font-mono text-xs">
                                            {record.result_code !== null
                                                ? record.result_code
                                                : '—'}
                                        </td>
                                        <td className="max-w-40 truncate px-3 py-3">
                                            {record.response_description ?? '—'}
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-3 text-xs">
                                            {new Date(record.created_at).toLocaleString()}
                                        </td>
                                        <td className="px-3 py-3 text-center">
                                            {(record.status === 'failed' ||
                                                record.status === 'timed_out') && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        handleRetry(record.id);
                                                    }}
                                                >
                                                    Retry
                                                </Button>
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
                            Callback #{expandedRow} — Full Payload
                        </h3>
                        <pre className="max-h-96 overflow-auto rounded-lg bg-muted p-3 text-xs">
                            {JSON.stringify(
                                data.data.find((r) => r.id === expandedRow)?.callback_payload ??
                                    'No payload stored',
                                null,
                                2,
                            )}
                        </pre>
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

function SummaryCard({
    label,
    value,
    className,
}: {
    label: string;
    value: number;
    className?: string;
}) {
    return (
        <div
            className={`rounded-xl border border-sidebar-border/70 p-4 ${className ?? ''}`}
        >
            <p className="text-2xl font-bold">{value}</p>
            <p className="text-xs text-muted-foreground">{label}</p>
        </div>
    );
}

CallbackMonitoring.layout = {
    breadcrumbs: [
        {
            title: 'Callback Monitoring',
            href: '/admin/callbacks',
        },
    ],
};
