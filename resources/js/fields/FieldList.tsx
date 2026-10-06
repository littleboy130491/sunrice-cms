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
        <div className="flex flex-col gap-4">
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

                return (
                    <div key={field.handle} className="grid gap-2">
                        {field.type !== 'toggle' && field.type !== 'fieldset' && (
                            <Label className="flex items-center gap-2">
                                <span>
                                    {field.label || field.handle}
                                    {field.required && <span className="text-destructive"> *</span>}
                                </span>
                                {shared && (
                                    <span className="inline-flex items-center gap-1 text-xs font-normal text-muted-foreground">
                                        <Languages className="size-3" /> Shared with {mainLocale?.toUpperCase() ?? 'the main language'}
                                    </span>
                                )}
                            </Label>
                        )}
                        {shared ? (
                            <fieldset disabled aria-disabled className="pointer-events-none opacity-60">
                                {control}
                            </fieldset>
                        ) : (
                            control
                        )}
                        {error && <p className="text-sm text-destructive">{error}</p>}
                    </div>
                );
            })}
        </div>
    );
}
