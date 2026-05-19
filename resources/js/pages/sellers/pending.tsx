import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import type { Seller, KycVerification, User } from '@/types';

interface PendingSeller {
    id: number;
    shop_name: string;
    verification_status: string;
    user: User & {
        kyc_verification: KycVerification | null;
    };
    created_at: string;
}

export default function PendingSellers({
    sellers,
}: {
    sellers: PendingSeller[];
}) {
    return (
        <>
            <Head title="Pending Sellers" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Pending Sellers"
                    description="Review seller upgrade requests"
                />

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-left">
                                <th className="px-4 py-3 font-medium">User</th>
                                <th className="px-4 py-3 font-medium">Phone</th>
                                <th className="px-4 py-3 font-medium">Shop Name</th>
                                <th className="px-4 py-3 font-medium">KYC Status</th>
                                <th className="px-4 py-3 font-medium">Shop Status</th>
                                <th className="px-4 py-3 font-medium">Submitted</th>
                                <th className="px-4 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sellers.map((seller) => (
                                <tr
                                    key={seller.id}
                                    className="border-b border-sidebar-border/70 last:border-0"
                                >
                                    <td className="px-4 py-3">{seller.user.name}</td>
                                    <td className="px-4 py-3">{seller.user.phone}</td>
                                    <td className="px-4 py-3">{seller.shop_name}</td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${
                                                seller.user.kyc_verification?.kyc_status === 'APPROVED'
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                    : seller.user.kyc_verification?.kyc_status === 'REJECTED'
                                                      ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                                      : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                            }`}
                                        >
                                            {seller.user.kyc_verification?.kyc_status ?? 'N/A'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${
                                                seller.verification_status === 'APPROVED'
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                    : seller.verification_status === 'REJECTED'
                                                      ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                                      : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                            }`}
                                        >
                                            {seller.verification_status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        {new Date(seller.created_at).toLocaleDateString()}
                                    </td>
                                    <td className="px-4 py-3">
                                        <a
                                            href={`/admin/sellers/${seller.id}/review`}
                                            className="text-emerald-600 hover:text-emerald-700 dark:text-emerald-500 dark:hover:text-emerald-400"
                                        >
                                            Review
                                        </a>
                                    </td>
                                </tr>
                            ))}
                            {sellers.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No pending seller requests.
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

PendingSellers.layout = {
    breadcrumbs: [
        {
            title: 'Sellers',
            href: '/admin/sellers/pending',
        },
        {
            title: 'Pending Sellers',
            href: '/admin/sellers/pending',
        },
    ],
};
