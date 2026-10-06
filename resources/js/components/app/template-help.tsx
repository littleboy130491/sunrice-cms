/** Blade view name → its file, e.g. `pages.landing` → resources/views/pages/landing.blade.php. */
export const viewFile = (view: string) => `resources/views/${view.replace(/\./g, '/')}.blade.php`;

/**
 * Explains a template setting: how to write a view name, and which files
 * are tried, in order, when it's left empty.
 */
export function TemplateHelp({ defaults, example = 'pages.landing' }: { defaults: string[]; example?: string }) {
    return (
        <div className="grid gap-1.5 text-xs leading-relaxed text-muted-foreground">
            <p>
                A Blade view name in your app, written with dots: <code className="text-foreground">{example}</code> uses{' '}
                <code>{viewFile(example)}</code>.
            </p>
            <div>
                <p>Leave empty to use the first of these that exists:</p>
                <ol className="mt-1 ml-4 list-decimal space-y-0.5">
                    {defaults.map((view) => (
                        <li key={view}>
                            <code>{viewFile(view)}</code>
                        </li>
                    ))}
                    <li>the built-in Sunrice template</li>
                </ol>
            </div>
        </div>
    );
}
