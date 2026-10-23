import { defineConfig } from 'vitest/config';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import path from 'path';

export default defineConfig({
    plugins: [svelte()],
    resolve: {
        conditions: ['browser'],
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        include: ['tests/frontend/**/*.test.ts'],
        environment: 'node',
    },
});
