import * as React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ExternalLink, Pencil, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { adminUrl } from '@/lib/route';
import { useCan } from '@/lib/can';
import type { SharedProps } from '@/types';

type ItemType = 'url' | 'entry' | 'collection' | 'term';

interface Item {
    id: number;
    parent_id: number | null;
    type: ItemType;
    target_id: number | null;
    target_title: string | null;
    target_taxonomy?: string | null;
    url: string | null;
    labels: Record<string, string>;
    new_tab: boolean;
}

interface Props {
    menu: { id: number; handle: string; title: string };
    items: Item[];
}

const TYPE_LABELS: Record<ItemType, string> = { url: 'URL', entry: 'Entry', collection: 'Collection archive', term: 'Term page' };

export default function MenuEdit({ menu, items }: Props) {
    const { adminPath, locales } = usePage<SharedProps>().props;
    const can = useCan();
    const canEdit = can('sunrice.menus.edit');
    const roots = items.filter((i) => i.parent_id === null);
    const childrenOf = (id: number) => items.filter((i) => i.parent_id === id);

    const remove = (id: number) =>
        window.confirm('Remove item?') && router.delete(adminUrl(`menu-items/${id}`, adminPath), { preserveScroll: true });

    const label = (i: Item) => i.labels?.[locales.main] || Object.values(i.labels ?? {})[0] || i.target_title || i.url || `#${i.id}`;

    // Move an item up or down among its siblings; the whole tree order is sent.
    const move = (item: Item, dir: -1 | 1) => {
        const siblings = item.parent_id === null ? roots : childrenOf(item.parent_id);
        const i = siblings.findIndex((s) => s.id === item.id);
        const j = i + dir;
        if (j < 0 || j >= siblings.length) return;
        const reordered = [...siblings];
        [reordered[i], reordered[j]] = [reordered[j], reordered[i]];
        const order: { id: number; parent_id: number | null }[] = [];
        for (const root of item.parent_id === null ? reordered : roots) {
            order.push({ id: root.id, parent_id: null });
            for (const child of item.parent_id === root.id ? reordered : childrenOf(root.id)) {
                order.push({ id: child.id, parent_id: root.id });
            }
        }
        router.post(adminUrl(`menus/${menu.id}/items/reorder`, adminPath), { items: order }, { preserveScroll: true });
    };

    const row = (item: Item, nested: boolean) => {
        const siblings = item.parent_id === null ? roots : childrenOf(item.parent_id);
        const position = siblings.findIndex((s) => s.id === item.id);

        return (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2">
            <span className="flex min-w-0 flex-1 items-center gap-2 text-sm">
                {canEdit ? (
                    <Link className="truncate font-medium hover:underline" href={adminUrl(`menu-items/${item.id}/edit`, adminPath)}>{label(item)}</Link>
                ) : (
                    <span className="truncate font-medium">{label(item)}</span>
                )}
                <Badge variant="secondary">{TYPE_LABELS[item.type]}</Badge>
                {item.type === 'url' ? (
                    <code className="truncate text-xs text-muted-foreground">{item.url}</code>
                ) : (
                    <span className="truncate text-xs text-muted-foreground">{item.target_title ?? 'Missing target'}</span>
                )}
                {item.new_tab && <ExternalLink className="size-3 shrink-0 text-muted-foreground" />}
            </span>
            {canEdit && (
                <span className="flex shrink-0 items-center gap-1">
                    <Button variant="ghost" size="sm" onClick={() => move(item, -1)} disabled={position <= 0} aria-label="Move up"><ArrowUp className="h-3.5 w-3.5" /></Button>
                    <Button variant="ghost" size="sm" onClick={() => move(item, 1)} disabled={position >= siblings.length - 1} aria-label="Move down"><ArrowDown className="h-3.5 w-3.5" /></Button>
                    <Button variant="ghost" size="sm" asChild aria-label="Edit"><Link href={adminUrl(`menu-items/${item.id}/edit`, adminPath)}><Pencil className="h-3.5 w-3.5" /></Link></Button>
                    {!nested && <Button variant="ghost" size="sm" asChild aria-label="Add sub-item"><Link href={adminUrl(`menus/${menu.id}/items/create?parent=${item.id}`, adminPath)}><Plus className="h-3.5 w-3.5" /></Link></Button>}
                    <Button variant="ghost" size="sm" className="text-destructive" onClick={() => remove(item.id)} aria-label="Remove"><Trash2 className="h-4 w-4" /></Button>
                </span>
            )}
        </div>
        );
    };

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h1 className="sunrice-page-title">{menu.title} <code className="text-sm text-muted-foreground">{menu.handle}</code></h1>
                {canEdit && <Button asChild><Link href={adminUrl(`menus/${menu.id}/items/create`, adminPath)}><Plus className="mr-1 h-4 w-4" /> Add item</Link></Button>}
            </div>

            <ul className="flex flex-col gap-1">
                {roots.map((item) => (
                    <li key={item.id}>
                        {row(item, false)}
                        <ul className="ml-6 mt-1 flex flex-col gap-1">
                            {childrenOf(item.id).map((child) => <li key={child.id}>{row(child, true)}</li>)}
                        </ul>
                    </li>
                ))}
                {roots.length === 0 && <li className="py-8 text-center text-sm text-muted-foreground">No items yet.</li>}
            </ul>

        </div>
    );
}
