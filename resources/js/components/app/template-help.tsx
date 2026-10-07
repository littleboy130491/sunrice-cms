/** Blade view name → its file, e.g. `pages.landing` → resources/views/pages/landing.blade.php. */
export const viewFile = (view: string) => `resources/views/${view.replace(/\./g, '/')}.blade.php`;

/**
 * Explains a template setting: how to write a view name, and which files
 * are tried, in order, when it's left empty.
 */
export function TemplateHelp({ defaults, example = 'pages.landing', lead }: { defaults: string[]; example?: string; lead?: string }) {
    return (
        <div className="grid gap-1.5 text-xs leading-relaxed text-muted-foreground">
            {lead && <p>{lead}</p>}
            <p>
                A Blade view name in your app, written with dots: <code className="text-foreground">{example}</code> uses{' '}
                <code className="break-all">{viewFile(example)}</code>.
            </p>
            <div>
                <p>Leave empty to use the first of these that exists:</p>
                <ol className="mt-1 ml-4 list-decimal space-y-0.5">
                    {defaults.map((view) => (
                        <li key={view}>
                            <code className="break-all">{viewFile(view)}</code>
                        </li>
                    ))}
                    <li>the built-in Sunrice template</li>
                </ol>
            </div>
        </div>
    );
}
