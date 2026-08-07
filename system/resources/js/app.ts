import './bootstrap.js';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/svelte';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { mount } from 'svelte';

const appName = import.meta.env.VITE_APP_NAME || 'NEXIAM';

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

        mount(App, { target: el, props });
    },
    progress: {
        color: '#2563eb',
    },
});
