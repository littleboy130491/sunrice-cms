import { Label } from '@/components/ui/label';
import type { AdminField, Json } from '@/types';
import { fieldComponents } from './registry';

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
 */
export default function FieldList({ fields, values, errors = {}, pathPrefix = 'data', onChange }: Props) {
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

                return (
                    <div key={field.handle} className="grid gap-2">
                        {field.type !== 'toggle' && field.type !== 'fieldset' && (
                            <Label>
                                {field.label || field.handle}
                                {field.required && <span className="text-destructive"> *</span>}
                            </Label>
                        )}
                        <Component
                            field={field}
                            value={values[field.handle]}
                            errors={errors}
                            pathPrefix={path}
                            onChange={(v: unknown) => onChange(field.handle, v)}
                        />
                        {error && <p className="text-sm text-destructive">{error}</p>}
                    </div>
                );
            })}
        </div>
    );
}
