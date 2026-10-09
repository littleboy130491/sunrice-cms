import '../css/admin.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import AppLayout from '@/layouts/AppLayout';
import { initializeAppearance } from '@/hooks/use-appearance';
import { installPluginApi } from '@/lib/plugin-api';
import { pluginScriptsLoaded, registeredPage } from '@/lib/page-registry';

type PageModule = {
    default: React.ComponentType<Record<string, unknown>> & {
        layout?: ((page: React.ReactNode) => React.ReactNode) | null;
    };
};

initializeAppearance();
// Before the first render: admin scripts (sunrice.admin.scripts) run after
// this bundle and register custom field components and package pages on
// window.Sunrice.
installPluginApi();

const brand = (window as unknown as { sunriceBrand?: string }).sunriceBrand || 'Sunrice';

createInertiaApp({
    title: (title) => (title && title !== brand ? `${title} · ${brand}` : brand),
    resolve: async (name) => {
        const pages = import.meta.glob<PageModule>('./pages/**/*.tsx', { eager: true });
        let page: PageModule | undefined = pages[`./pages/${name}.tsx`];
        // A package's page (window.Sunrice.registerPage), once its script has run.
        if (!page) {
            if (!registeredPage(name)) {
                await pluginScriptsLoaded();
            }
            const component = registeredPage(name);
            page = component ? ({ default: component } as PageModule) : undefined;
        }
        if (!page) {
            throw new Error(`Page not found: ${name}. A package's page must be registered with window.Sunrice.registerPage('${name}', Component).`);
        }

        // Pages may opt out of the admin layout (Auth pages set their own).
        if (page.default.layout === null) {
            return page;
        }
        page.default.layout = page.default.layout ?? ((p: React.ReactNode) => <AppLayout>{p}</AppLayout>);

        return page;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#71717a' },
});
