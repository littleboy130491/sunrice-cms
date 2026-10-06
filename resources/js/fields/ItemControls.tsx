import { Eye, EyeOff, X } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

interface Props {
    id: string;
    itemKey: string | null | undefined;
    hidden: boolean;
    /** Secondary language: keys, visibility and removal belong to the main language. */
    locked: boolean;
    onKeyChange: (key: string) => void;
    onHiddenChange: (hidden: boolean) => void;
    onRemove: () => void;
}

/**
 * Key, Show switch and remove button shown on every repeater row and
 * flexible block. Templates fetch keyed items with `->byKey('hero')`;
 * hidden items are skipped on the site.
 */
export default function ItemControls({ id, itemKey, hidden, locked, onKeyChange, onHiddenChange, onRemove }: Props) {
    const switchId = `show-${id}`;

    return (
        <div className="flex items-center gap-3">
            <Input
                value={itemKey ?? ''}
                onChange={(e) => onKeyChange(e.target.value.toLowerCase().replace(/[^a-z0-9_-]+/g, '_'))}
                placeholder="key"
                aria-label="Key"
                title={`Optional key for templates: ->byKey('${itemKey || 'key'}')`}
                disabled={locked}
                className="h-7 w-32 font-mono text-xs"
            />
            <label htmlFor={switchId} className={cn('flex items-center gap-1.5 text-xs text-muted-foreground', locked && 'opacity-60')}>
                {hidden ? <EyeOff className="size-3.5" /> : <Eye className="size-3.5" />}
                Show
                <Switch id={switchId} size="sm" checked={!hidden} onCheckedChange={(v) => onHiddenChange(!v)} disabled={locked} />
            </label>
            {!locked && (
                <button type="button" className="grid size-6 place-items-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive" onClick={onRemove} aria-label="Remove">
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}
