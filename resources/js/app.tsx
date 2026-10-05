import '../css/admin.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import AppLayout from '@/layouts/AppLayout';

type PageModule = {
    default: React.ComponentType<Record<string, unknown>> & {
        layout?: (page: React.ReactNode) => React.ReactNode;
    };
};

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob<PageModule>('./pages/**/*.tsx', { eager: true });
        const page = pages[`./pages/${name}.tsx`];
        if (!page) {
            throw new Error(`Page not found: ${name}`);
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
    progress: { color: '#18181b' },
});
