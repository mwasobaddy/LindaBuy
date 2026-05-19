import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Seller, KycVerification, User } from '@/types';

interface SellerWithRelations extends Seller {
    user: User & {
        kyc_verification: KycVerification | null;
    };
}

export default function SellerReview({ seller }: { seller: SellerWithRelations }) {
    const { auth } = usePage().props;

    const [rejectKycOpen, setRejectKycOpen] = useState(false);
    const [rejectShopOpen, setRejectShopOpen] = useState(false);
    const [rejectionReason, setRejectionReason] = useState('');
    const [actionLoading, setActionLoading] = useState<string | null>(null);

    const kyc = seller.user.kyc_verification;

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
            <Head title="Review Seller" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={`Review: ${seller.shop_name}`}
                    description={`Seller: ${seller.user.name} (${seller.user.phone})`}
                />

                <div className="grid gap-6 md:grid-cols-2">
                    <div className="rounded-xl border border-sidebar-border/70 p-4">
                        <Heading
                            variant="small"
                            title="KYC Verification"
                            description="Personal identity documents"
                        />

                        <div className="mt-4 space-y-2 text-sm">
                            <p>
                                <span className="font-medium">ID Number:</span>{' '}
                                {kyc?.id_number ?? 'N/A'}
                            </p>
                            <p>
                                <span className="font-medium">Status:</span>{' '}
                                <span
                                    className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${
                                        kyc?.kyc_status === 'APPROVED'
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : kyc?.kyc_status === 'REJECTED'
                                              ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                              : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                    }`}
                                >
                                    {kyc?.kyc_status ?? 'N/A'}
                                </span>
                            </p>
                            {kyc?.kyc_status !== 'APPROVED' && (
                                <div className="mt-4 flex gap-2">
                                    <Button
                                        variant="default"
                                        onClick={() =>
                                            performAction(
                                                `/api/admin/sellers/${seller.id}/approve-kyc`,
                                                'PATCH'
                                            )
                                        }
                                        disabled={actionLoading !== null}
                                    >
                                        {actionLoading === `/api/admin/sellers/${seller.id}/approve-kyc` ? (
                                            <Spinner />
                                        ) : null}
                                        Approve KYC
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        onClick={() => setRejectKycOpen(!rejectKycOpen)}
                                    >
                                        Reject KYC
                                    </Button>
                                </div>
                            )}
                            {kyc?.rejected_reason && (
                                <p className="mt-2 text-red-600 dark:text-red-400">
                                    <span className="font-medium">Reason:</span>{' '}
                                    {kyc.rejected_reason}
                                </p>
                            )}
                            {rejectKycOpen && (
                                <div className="mt-4 space-y-2">
                                    <Label htmlFor="reject-kyc-reason">Rejection Reason</Label>
                                    <Input
                                        id="reject-kyc-reason"
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
                                                    `/api/admin/sellers/${seller.id}/reject-kyc`,
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

                    <div className="rounded-xl border border-sidebar-border/70 p-4">
                        <Heading
                            variant="small"
                            title="Shop Verification"
                            description="Shop details"
                        />

                        <div className="mt-4 space-y-2 text-sm">
                            <p>
                                <span className="font-medium">Shop Name:</span>{' '}
                                {seller.shop_name}
                            </p>
                            <p>
                                <span className="font-medium">Location:</span>{' '}
                                {seller.shop_location ?? 'Not provided'}
                            </p>
                            <p>
                                <span className="font-medium">Status:</span>{' '}
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
                            </p>
                            {seller.verification_status !== 'APPROVED' && (
                                <div className="mt-4 flex gap-2">
                                    <Button
                                        variant="default"
                                        onClick={() =>
                                            performAction(
                                                `/api/admin/sellers/${seller.id}/approve-shop`,
                                                'PATCH'
                                            )
                                        }
                                        disabled={actionLoading !== null}
                                    >
                                        {actionLoading === `/api/admin/sellers/${seller.id}/approve-shop` ? (
                                            <Spinner />
                                        ) : null}
                                        Approve Shop
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        onClick={() => setRejectShopOpen(!rejectShopOpen)}
                                    >
                                        Reject Shop
                                    </Button>
                                </div>
                            )}
                            {seller.rejected_reason && (
                                <p className="mt-2 text-red-600 dark:text-red-400">
                                    <span className="font-medium">Reason:</span>{' '}
                                    {seller.rejected_reason}
                                </p>
                            )}
                            {rejectShopOpen && (
                                <div className="mt-4 space-y-2">
                                    <Label htmlFor="reject-shop-reason">Rejection Reason</Label>
                                    <Input
                                        id="reject-shop-reason"
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
                                                    `/api/admin/sellers/${seller.id}/reject-shop`,
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
            </div>
        </>
    );
}

SellerReview.layout = {
    breadcrumbs: [
        {
            title: 'Sellers',
            href: '/admin/sellers/pending',
        },
        {
            title: 'Review',
            href: '#',
        },
    ],
};
