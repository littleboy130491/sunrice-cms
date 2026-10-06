import { Languages } from 'lucide-react';
import { Label } from '@/components/ui/label';
import type { AdminField, Json } from '@/types';
import { fieldComponents } from './registry';
import { useTranslationMode } from './translation-mode';

interface Props {
    fields: AdminField[];
    values: Record<string, Json>;
    errors?: Record<string, string>;
    pathPrefix?: string;
    onChange: (handle: string, value: unknown) => void;
}

/**
 * Renders a list of admin-schema fields against a values object.
 * Error keys use Laravel dot paths (data.field.0.sub).
 *
 * When editing a secondary language, fields that aren't translatable
 * are shown read-only: they always come from the main language.
 */
export default function FieldList({ fields, values, errors = {}, pathPrefix = 'data', onChange }: Props) {
    const { secondary, mainLocale } = useTranslationMode();

    return (
        <div className="flex flex-col gap-5">
            {fields.map((field) => {
                const Component = fieldComponents[field.display_type ?? field.type] ?? fieldComponents[field.type];
                if (!Component) {
                    return (
                        <p key={field.handle} className="text-sm text-muted-foreground">
                            Unknown field type: {field.type}
                        </p>
                    );
                }
                const path = `${pathPrefix}.${field.handle}`;
                const error = errors[path] ?? errors[`${pathPrefix}s.${field.handle}`];

                const shared = secondary && field.translatable === false;
                const control = (
                    <Component
                        field={field}
                        value={values[field.handle]}
                        errors={errors}
                        pathPrefix={path}
                        onChange={(v: unknown) => onChange(field.handle, v)}
                    />
                );

                const labelled = field.type !== 'toggle' && field.type !== 'fieldset';
                const instructions = typeof field.instructions === 'string' ? field.instructions.trim() : '';

                return (
                    <div key={field.handle} className="grid gap-2">
                        {labelled && (
                            <Label className="flex items-center gap-2">
                                <span>
                                    {field.label || field.handle}
                                    {field.required && (
                                        <span className="ml-0.5 text-destructive/80" aria-hidden>
                                            *
                                        </span>
                                    )}
                                </span>
                                {shared && (
                                    <span className="inline-flex items-center gap-1 text-xs font-normal text-muted-foreground">
                                        <Languages className="size-3" /> Shared with {mainLocale?.toUpperCase() ?? 'the main language'}
                                    </span>
                                )}
                            </Label>
                        )}
                        {instructions && labelled && <p className="-mt-1 text-[13px] leading-snug text-muted-foreground">{instructions}</p>}
                        {shared ? (
                            <fieldset disabled aria-disabled className="pointer-events-none opacity-60">
                                {control}
                            </fieldset>
                        ) : (
                            control
                        )}
                        {instructions && !labelled && <p className="text-[13px] leading-snug text-muted-foreground">{instructions}</p>}
                        {error && <p className="text-[13px] text-destructive">{error}</p>}
                    </div>
                );
            })}
        </div>
    );
}
