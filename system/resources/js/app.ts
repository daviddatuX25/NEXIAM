import './bootstrap.js';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/svelte';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { mount } from 'svelte';
import { initializeTheme, disposeTheme } from './lib/theme.svelte';
import { initializeScale } from './lib/app-scale.svelte';
import { initializeNoticeStudioHistoryGuard } from './lib/noticeStudioHistoryGuard';
import ApplicationRoot from './Components/ApplicationRoot.svelte';

declare global {
    interface Window {
        __dismissNexiamSplash?: () => void;
    }
}

initializeTheme();
initializeScale();

if (import.meta.hot) {
    import.meta.hot.dispose(() => {
        disposeTheme();
    });
}
const appName = import.meta.env.VITE_APP_NAME || 'NEXIAM';
initializeNoticeStudioHistoryGuard();


createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.svelte`,
            import.meta.glob('./Pages/**/*.svelte'),
        ),
    setup({ el, App, props }) {
        if (!el) {
            throw new Error('Inertia mount element was not found');
        }

        mount(ApplicationRoot, { target: el, props: { App, appProps: props } });

        if (typeof window.__dismissNexiamSplash === 'function') {
            window.__dismissNexiamSplash();
        }
    },
    progress: {
        color: '#2563eb',
    },
});
