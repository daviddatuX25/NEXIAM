import js from '@eslint/js';
import svelte from 'eslint-plugin-svelte';
import globals from 'globals';
import prettier from 'eslint-config-prettier';
import { plugin as shadcn } from '@shadcn/lint';
import svelteParser from 'svelte-eslint-parser';
import tsParser from '@typescript-eslint/parser';

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
        languageOptions: {
            globals: globals.browser,
            parser: svelteParser,
            parserOptions: { parser: tsParser },
        },
        plugins: { shadcn },
        settings: {
            shadcn: {
                ui: ['@/Components/ui'],
            },
        },
        rules: {
            'shadcn/no-restyle': [
                'warn',
                {
                    allow: ['layout'],
                    contracts: [
                        { pattern: '^Button$', allow: ['layout', 'w-full', 'w-fit', 'gap-*', 'active:scale-*'] },
                        { pattern: '^DialogContent$', allow: ['layout', 'max-w-*', 'p-*', 'bg-*', 'border-*', 'rounded-*', 'shadow-*'] },
                    ],
                },
            ],
            'shadcn/no-raw-colors': [
                'warn',
                {
                    allow: ['*-emerald-*', '*-slate-*', '*-amber-*', '*-rose-*', '*-cyan-*'],
                },
            ],
            'shadcn/no-arbitrary-values': 'warn',
        },
    },
    {
        files: ['resources/js/Components/ui/**/*.svelte'],
        rules: {
            'shadcn/no-restyle': 'off',
        },
    },
    {
        files: ['vite.config.ts', 'vitest.config.ts', 'playwright.config.ts'],
        languageOptions: { globals: globals.node },
    },
    ...svelte.configs['flat/recommended'],
    ...svelte.configs['flat/prettier'],
    prettier,
];
