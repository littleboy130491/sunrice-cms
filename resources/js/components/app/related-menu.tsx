import { Link } from '@inertiajs/react';
import { EllipsisVertical, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export interface RelatedLink {
    group: string;
    label: string;
    href: string;
    external?: boolean;
}

/** ⋮ menu of pages related to the one being edited (list, settings, blueprint…). */
export function RelatedMenu({ links }: { links?: RelatedLink[] }) {
    if (!links || links.length === 0) return null;
    const groups = links.reduce<Record<string, RelatedLink[]>>((acc, link) => {
        (acc[link.group] ??= []).push(link);
        return acc;
    }, {});

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="outline" size="icon" aria-label="Go to related pages" title="Go to…">
                    <EllipsisVertical />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-60">
                {Object.entries(groups).map(([group, items], i) => (
                    <DropdownMenuGroup key={group}>
                        {i > 0 && <DropdownMenuSeparator />}
                        <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">{group}</DropdownMenuLabel>
                        {items.map((link) => (
                            <DropdownMenuItem key={link.href + link.label} asChild>
                                {link.external ? (
                                    <a href={link.href} target="_blank" rel="noopener">
                                        <span className="truncate">{link.label}</span> <ExternalLink className="ml-auto" />
                                    </a>
                                ) : (
                                    <Link href={link.href}><span className="truncate">{link.label}</span></Link>
                                )}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuGroup>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
