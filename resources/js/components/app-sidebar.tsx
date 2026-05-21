import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    BookOpen,
    FileText,
    FolderGit2,
    LayoutGrid,
    RefreshCw,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;
    const user = auth?.user as {
        role?: string;
        permissions?: string[];
        is_admin?: boolean;
    } | undefined;
    const permissions = user?.permissions ?? [];
    const isAdmin = user?.is_admin ?? false;
    const role = user?.role ?? 'buyer';
    const dashboardUrl = dashboard();

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        {
            title: 'Orders',
            href: '/orders',
            icon: ShoppingCart,
        },
        {
            title: 'Wallet',
            href: '/wallet',
            icon: Wallet,
        },
        {
            title: 'Settings',
            href: '/settings/profile',
            icon: Settings,
        },
    ];

    if (role === 'seller:approved' || permissions.includes('view-income')) {
        mainNavItems.push({
            title: 'Withdrawals',
            href: '/seller/withdrawals',
            icon: Banknote,
        });

        mainNavItems.push({
            title: 'Templates',
            href: '/seller/templates',
            icon: FileText,
        });
    }

    if (role === 'agent:approved' || permissions.includes('verify-orders')) {
        mainNavItems.push({
            title: 'Agent Dashboard',
            href: '/agent/dashboard',
            icon: ShieldCheck,
        });
    }

    if (isAdmin || permissions.includes('approve-sellers') || permissions.includes('approve-agents')) {
        mainNavItems.push(
            {
                title: 'Orders',
                href: '/admin/orders',
                icon: ShoppingCart,
            },
            {
                title: 'Sellers',
                href: '/admin/sellers/pending',
                icon: ShieldCheck,
            },
            {
                title: 'Agents',
                href: '/admin/agents/pending',
                icon: Users,
            },
            {
                title: 'Withdrawals',
                href: '/admin/withdrawals',
                icon: Banknote,
            },
            {
                title: 'Callbacks',
                href: '/admin/callbacks',
                icon: RefreshCw,
            },
            {
                title: 'Failed Reversals',
                href: '/admin/failed-reversals',
                icon: Banknote,
            },
            {
                title: 'Activity Logs',
                href: '/admin/activity-logs',
                icon: FileText,
            },
            {
                title: 'Issue Reports',
                href: '/admin/issue-reports',
                icon: AlertTriangle,
            },
            {
                title: 'Settings',
                href: '/admin/settings',
                icon: Settings,
            }
        );
    }

    const footerNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGit2,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
