import * as React from 'react';

interface HeadingProps {
    title: React.ReactNode;
    description?: React.ReactNode;
    actions?: React.ReactNode;
}

/** Page title block used at the top of every admin page. */
export function Heading({ title, description, actions }: HeadingProps) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-4">
            <div className="space-y-2">
                <h1 className="sunrice-page-title">{title}</h1>
                {description && <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
