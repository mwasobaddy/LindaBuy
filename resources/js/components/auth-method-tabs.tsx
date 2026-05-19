import { Link } from '@inertiajs/react';

export type AuthMethodTab = {
    id: string;
    label: string;
    href: string;
};

type AuthMethodTabsProps = {
    tabs: AuthMethodTab[];
    activeTab: string;
};

export default function AuthMethodTabs({ tabs, activeTab }: AuthMethodTabsProps) {
    return (
        <div className="flex rounded-lg p-1 gap-4">
            {tabs.map((tab) => (
                <Link
                    key={tab.id}
                    href={tab.href}
                    className={`flex-1 rounded-full px-3 py-2 text-center text-sm font-medium transition-colors ${
                        activeTab === tab.id
                            ? 'bg-emerald-500 text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground hover:bg-muted border-b-2 border-emerald-500 rounded-none hover:rounded-t-xl'
                    }`}
                >
                    {tab.label}
                </Link>
            ))}
        </div>
    );
}
