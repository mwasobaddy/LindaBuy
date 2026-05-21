import type { Auth } from '@/types/auth';
import type { Team } from '@/types/teams';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

import type Echo from 'laravel-echo';

declare global {
    interface Window {
        Pusher: typeof import('pusher-js').default;
        Echo: Echo;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth & {
                user?: Auth['user'] & {
                    role?: string;
                    permissions?: string[];
                    is_admin?: boolean;
                };
            };
            sidebarOpen: boolean;
            currentTeam: Team | null;
            teams: Team[];
            wallet_balance?: { available: number; ledger: number };
            recent_transactions?: unknown[];
            receivable_balance?: number;
            withdrawals?: unknown[];
            orders?: unknown[];
            order?: unknown;
            templates?: unknown[];
            failed_reversals?: unknown[];
            initial_summary?: { action: string; count: number }[];
            callbacks?: { data: unknown[]; meta?: unknown };
            settings?: Record<string, string | number | boolean>;
            [key: string]: unknown;
        };
    }
}
