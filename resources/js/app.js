import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/svelte';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { mount } from 'svelte';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./Pages/${name}.svelte`,
            import.meta.glob('./Pages/**/*.svelte')
        );
        return { default: page.default ?? page, layout: page.layout };
    },
    setup({ el, App, props }) {
        // Svelte 5 recommended: use mount() instead of new App()
        mount(App, { target: el, props });
    },
    progress: {
        color: '#4B5563',
    },
});
