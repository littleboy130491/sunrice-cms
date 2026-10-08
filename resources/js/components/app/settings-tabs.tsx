import { Link, usePage } from '@inertiajs/react';
import { adminUrl } from '@/lib/route';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

/** General | SEO switcher at the top of the settings pages. */
export function SettingsTabs({ current }: { current: 'general' | 'seo' }) {
    const { adminPath } = usePage<SharedProps>().props;
    const tabs = [
        { key: 'general', label: 'General', href: adminUrl('settings', adminPath) },
        { key: 'seo', label: 'SEO', href: adminUrl('settings/seo', adminPath) },
    ];

    return (
        <nav className="inline-flex w-fit rounded-lg bg-muted p-1 text-sm" aria-label="Settings sections">
            {tabs.map((tab) => (
                <Link
                    key={tab.key}
                    href={tab.href}
                    aria-current={current === tab.key ? 'page' : undefined}
                    className={cn('rounded-md px-3 py-1 font-medium text-muted-foreground', current === tab.key && 'bg-background text-foreground shadow-sm')}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
    );
}
