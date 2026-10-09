import type * as React from 'react';

/**
 * Admin pages from packages (window.Sunrice.registerPage): a package's
 * controller renders `Inertia::render('Commerce/Orders/Show')` and its
 * admin script registers the component under that name.
 */
export type PageComponent = React.ComponentType<Record<string, unknown>> & {
    layout?: ((page: React.ReactNode) => React.ReactNode) | null;
};

const pages: Record<string, PageComponent> = {};

export function registerPage(name: string, component: PageComponent): void {
    pages[name] = component;
}

export function registeredPage(name: string): PageComponent | undefined {
    return pages[name];
}

/**
 * Admin scripts run after the admin's own bundle, so on the first page
 * load a package's page may not be registered yet: wait until every
 * script on the page has run.
 */
export function pluginScriptsLoaded(): Promise<void> {
    if (document.readyState === 'complete') {
        return Promise.resolve();
    }

    return new Promise((resolve) => window.addEventListener('load', () => resolve(), { once: true }));
}
