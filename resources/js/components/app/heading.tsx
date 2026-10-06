import * as React from 'react';

interface HeadingProps {
    title: React.ReactNode;
    description?: React.ReactNode;
    actions?: React.ReactNode;
}

/** Page title block used at the top of every admin page. */
export function Heading({ title, description, actions }: HeadingProps) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="space-y-1">
                <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                {description && <p className="text-sm text-muted-foreground">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
