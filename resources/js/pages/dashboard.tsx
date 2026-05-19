import { Head, Link, usePage } from '@inertiajs/react';
import { BadgeCheck, Handshake, PackageOpen, ShoppingBag, Store, UserCheck, Wallet } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

export default function Dashboard() {
    const { auth } = usePage().props;
    const role = (auth?.user as { role?: string } | undefined)?.role ?? 'buyer';

    if (role === 'buyer') {
        return (
            <>
                <Head title="Dashboard" />

                <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                    <div>
                        <h2 className="text-xl font-semibold tracking-tight">Welcome to LindaBuy</h2>
                        <p className="text-sm text-muted-foreground">
                            Choose how you want to participate in the marketplace
                        </p>
                    </div>

                    <div className="grid gap-6 md:grid-cols-2">
                        <Card className="border-emerald-200 dark:border-emerald-800">
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900">
                                        <Store className="size-5 text-emerald-600 dark:text-emerald-400" />
                                    </div>
                                    <div>
                                        <CardTitle>Become a Seller</CardTitle>
                                        <CardDescription>Turn your network into income</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm text-muted-foreground">
                                    List products, manage orders, and grow your business on LindaBuy.
                                    Set up your shop with a few details and start selling to thousands of
                                    buyers across the platform.
                                </p>
                                <ul className="space-y-2 text-sm">
                                    {[
                                        { icon: ShoppingBag, text: 'Create your shop and list products' },
                                        { icon: Wallet, text: 'Earn directly from every sale' },
                                        { icon: PackageOpen, text: 'Manage orders & track deliveries' },
                                    ].map(({ icon: Icon, text }) => (
                                        <li key={text} className="flex items-center gap-2">
                                            <Icon className="size-4 text-emerald-600 dark:text-emerald-400" />
                                            <span>{text}</span>
                                        </li>
                                    ))}
                                </ul>
                                <Button asChild className="w-full">
                                    <Link href="/seller/request">Get Started as a Seller</Link>
                                </Button>
                            </CardContent>
                        </Card>

                        <Card className="border-sky-200 dark:border-sky-800">
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-sky-100 dark:bg-sky-900">
                                        <UserCheck className="size-5 text-sky-600 dark:text-sky-400" />
                                    </div>
                                    <div>
                                        <CardTitle>Become an Agent</CardTitle>
                                        <CardDescription>Earn by helping others shop</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm text-muted-foreground">
                                    Assist buyers who prefer offline or assisted shopping. Process their
                                    orders, handle deliveries, and earn commissions on every successful
                                    transaction you facilitate.
                                </p>
                                <ul className="space-y-2 text-sm">
                                    {[
                                        { icon: Handshake, text: 'Assist buyers with their purchases' },
                                        { icon: BadgeCheck, text: 'Process orders on behalf of others' },
                                        { icon: Wallet, text: 'Earn commissions per transaction' },
                                    ].map(({ icon: Icon, text }) => (
                                        <li key={text} className="flex items-center gap-2">
                                            <Icon className="size-4 text-sky-600 dark:text-sky-400" />
                                            <span>{text}</span>
                                        </li>
                                    ))}
                                </ul>
                                <Button asChild className="w-full">
                                    <Link href="/agent/request">Get Started as an Agent</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="relative min-h-[60vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
