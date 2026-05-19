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
            [key: string]: unknown;
        };
    }
}
