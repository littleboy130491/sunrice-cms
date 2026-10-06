import { usePage } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { InputError } from '@/components/app/input-error';
import type { SharedProps } from '@/types';

interface Props {
    /** {locale: title} for the non-main languages. */
    value: Record<string, string>;
    onChange: (value: Record<string, string>) => void;
    /** The main-language title, shown as the fallback. */
    mainTitle: string;
    errors: Record<string, string>;
    /** Error key prefix, e.g. `settings.titles`. */
    errorPrefix: string;
}

/**
 * Title inputs for each non-main language. Empty ones use the
 * main-language title on the site. Renders nothing on one-language sites.
 */
export default function TranslatedTitles({ value, onChange, mainTitle, errors, errorPrefix }: Props) {
    const { locales } = usePage<SharedProps>().props;
    const others = locales.available.filter((lc) => lc !== locales.main);
    if (others.length === 0) return null;

    return (
        <>
            {others.map((lc) => (
                <div key={lc} className="grid gap-2">
                    <Label htmlFor={`title_${lc}`}>Title ({locales.names[lc] ?? lc.toUpperCase()})</Label>
                    <Input
                        id={`title_${lc}`}
                        value={value[lc] ?? ''}
                        placeholder={mainTitle || `Uses the ${locales.main.toUpperCase()} title`}
                        onChange={(e) => onChange({ ...value, [lc]: e.target.value })}
                    />
                    <InputError message={errors[`${errorPrefix}.${lc}`]} />
                </div>
            ))}
        </>
    );
}
