import js from '@eslint/js';
import svelte from 'eslint-plugin-svelte';
import globals from 'globals';
import prettier from 'eslint-config-prettier';

export default [
    {
        ignores: ['vendor/', 'public/build/', 'node_modules/', '../legacy_codebase/'],
    },
    js.configs.recommended,
    {
        files: ['resources/**/*.{js,ts}', 'tests/**/*.{js,ts}'],
        languageOptions: { globals: globals.browser },
        rules: { 'no-unused-vars': 'error' },
    },
    {
        files: ['**/*.svelte'],
        languageOptions: { globals: globals.browser },
    },
    {
        files: ['vite.config.ts', 'vitest.config.ts', 'playwright.config.ts'],
        languageOptions: { globals: globals.node },
    },
    ...svelte.configs['flat/recommended'],
    ...svelte.configs['flat/prettier'],
    prettier,
];
