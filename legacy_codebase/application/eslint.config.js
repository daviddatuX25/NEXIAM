import js from '@eslint/js';
import svelte from 'eslint-plugin-svelte';
import globals from 'globals';
import prettier from 'eslint-config-prettier';

export default [
    {
        ignores: ['vendor/', 'build/', 'node_modules/', 'legacy_codebase/', 'manuscript/'],
    },
    js.configs.recommended,
    {
        files: ['resources/**/*.{js,mjs}'],
        languageOptions: { globals: globals.browser },
    },
    {
        files: ['**/*.svelte'],
        languageOptions: { globals: globals.browser },
    },
    {
        files: ['vite.config.js', 'scripts/**/*.{js,cjs,mjs}', '*.{cjs,mjs}'],
        languageOptions: { globals: globals.node },
    },
    ...svelte.configs['flat/recommended'],
    ...svelte.configs['flat/prettier'],
    prettier,
];
