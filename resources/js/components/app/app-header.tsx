import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useNavMatch } from '@/components/app/use-nav-match';
import { useBreadcrumbOverride } from '@/components/app/breadcrumbs';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

export function AppHeader() {
    const { adminPath } = usePage<SharedProps>().props;
    const match = useNavMatch();
    const override = useBreadcrumbOverride();
    const path = usePage().url.split('?')[0];
    const itemUrl = match ? adminUrl(match.item.href, adminPath) : null;
    const isDeeper = itemUrl !== null && path !== itemUrl;

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-border/60 px-5 md:px-8 lg:px-10">
            <SidebarTrigger className="-ml-1" />
            <Separator orientation="vertical" className="mr-2 data-[orientation=vertical]:h-4" />
            <Breadcrumb>
                <BreadcrumbList>
                    {override ? (
                        override.map((crumb, i) => (
                            <React.Fragment key={i}>
                                {i > 0 && <BreadcrumbSeparator className={i === 1 ? 'hidden md:block' : undefined} />}
                                <BreadcrumbItem className={i === 0 && override.length > 1 ? 'hidden md:block' : undefined}>
                                    {crumb.href && i < override.length - 1 ? (
                                        <BreadcrumbLink asChild>
                                            <Link href={crumb.href}>{crumb.label}</Link>
                                        </BreadcrumbLink>
                                    ) : (
                                        <BreadcrumbPage className="max-w-48 truncate">{crumb.label}</BreadcrumbPage>
                                    )}
                                </BreadcrumbItem>
                            </React.Fragment>
                        ))
                    ) : match === null ? (
                        <BreadcrumbItem>
                            <BreadcrumbPage>Dashboard</BreadcrumbPage>
                        </BreadcrumbItem>
                    ) : (
                        <>
                            <BreadcrumbItem className="hidden md:block">{match.group.label}</BreadcrumbItem>
                            <BreadcrumbSeparator className="hidden md:block" />
                            <BreadcrumbItem>
                                {isDeeper ? (
                                    <BreadcrumbLink asChild>
                                        <Link href={itemUrl!}>{match.item.label}</Link>
                                    </BreadcrumbLink>
                                ) : (
                                    <BreadcrumbPage>{match.item.label}</BreadcrumbPage>
                                )}
                            </BreadcrumbItem>
                            {isDeeper && (
                                <React.Fragment>
                                    <BreadcrumbSeparator />
                                    <BreadcrumbItem>
                                        <BreadcrumbPage>{path.endsWith('/create') ? 'New' : 'Edit'}</BreadcrumbPage>
                                    </BreadcrumbItem>
                                </React.Fragment>
                            )}
                        </>
                    )}
                </BreadcrumbList>
            </Breadcrumb>
        </header>
    );
}
