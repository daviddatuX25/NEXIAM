// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { createRawSnippet } from 'svelte';
import { render } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type * as Inertia from '@inertiajs/svelte';
import HomeLayout from '../../resources/js/Layouts/HomeLayout.svelte';
import {
    disposeTheme,
    getResolvedTheme,
    getThemePreference,
    initializeTheme,
    isDarkMode,
    setThemePreference,
    toggleTheme,
} from '../../resources/js/lib/theme.svelte';

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<typeof Inertia>();
    return {
        ...actual,
        usePage: () => ({
            url: '/',
            props: {
                auth: { user: null },
            },
        }),
    };
});

describe('Homepage theme toggle and top-bar logo redirect', () => {
    const homeLayoutSource = readFileSync(
        path.resolve(process.cwd(), 'resources/js/Layouts/HomeLayout.svelte'),
        'utf8',
    );
    const applicantLayoutSource = readFileSync(
        path.resolve(process.cwd(), 'resources/js/Pages/Applicant/ApplicantLayout.svelte'),
        'utf8',
    );
    const staffLayoutSource = readFileSync(
        path.resolve(process.cwd(), 'resources/js/Pages/Staff/StaffLayout.svelte'),
        'utf8',
    );
    const authLayoutSource = readFileSync(
        path.resolve(process.cwd(), 'resources/js/Layouts/AuthenticatedLayout.svelte'),
        'utf8',
    );

    beforeEach(() => {
        localStorage.clear();
        document.documentElement.className = '';
        window.matchMedia = vi.fn().mockImplementation((query: string) => ({
            matches: false,
            media: query,
            onchange: null,
            addListener: vi.fn(),
            removeListener: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            dispatchEvent: vi.fn(),
        }));
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                unobserve() {}
                disconnect() {}
            },
        );
    });

    afterEach(() => {
        disposeTheme();
        localStorage.clear();
        document.documentElement.className = '';
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    describe('HomeLayout source contracts', () => {
        it('does not force setThemeOverride("system") on mount', () => {
            expect(homeLayoutSource).not.toContain("setThemeOverride('system')");
            expect(homeLayoutSource).not.toContain('setThemeOverride(null)');
        });

        it('provides PreferencesMenu for display and accessibility preferences without redundant desktop standalone toggles', () => {
            expect(homeLayoutSource).toContain('<PreferencesMenu variant="public" />');
        });

        it('redirects the top bar logo link directly to the public homepage ("/")', () => {
            // Main logo brand Link uses href="/"
            expect(homeLayoutSource).toMatch(/<Link\s+href="\/"[\s\S]*?aria-label=\{`\$\{institution\.name\} home`\}/);
            expect(homeLayoutSource).toContain('handleLogoClick');
            expect(homeLayoutSource).toContain('handleLogoBefore');
        });
    });

    describe('ApplicantLayout source contracts', () => {
        it('redirects mobile top-bar brand logo and sidebar brand logo to public homepage ("/")', () => {
            // Mobile brand logo
            expect(applicantLayoutSource).toMatch(/<!-- Mobile brand logo -->\s*<Link\s+href="\/"/);
            // Sidebar brand logo
            expect(applicantLayoutSource).toMatch(/<!-- SIDEBAR BRAND \/ HEADER -->[\s\S]*?<Link\s+href="\/"/);
        });

        it('preserves applicant portal destination for menu items and breadcrumbs', () => {
            // Breadcrumb Home still takes applicant back to their portal
            expect(applicantLayoutSource).toMatch(/<Link href="\/applicant\/portal"[^>]*>Home<\/Link>/);
        });
    });

    describe('StaffLayout and AuthenticatedLayout source contracts', () => {
        it('redirects StaffLayout sidebar logo and top-bar wordmark to public homepage ("/")', () => {
            // Sidebar logo
            expect(staffLayoutSource).toMatch(/<Link href="\/"[^>]*title="NEXIAM Public Homepage"/);
            // Header breadcrumb-fallback logo
            expect(staffLayoutSource).toMatch(/\{#if breadcrumbs\.length === 0\}\s*<Link href="\/"/);
        });

        it('redirects AuthenticatedLayout sidebar logo and top-bar wordmark to public homepage ("/")', () => {
            // Sidebar logo
            expect(authLayoutSource).toMatch(/<Link href="\/"[^>]*title="NEXIAM Public Homepage"/);
            // Header breadcrumb-fallback logo
            expect(authLayoutSource).toMatch(/\{#if breadcrumbs\.length === 0\}\s*<Link href="\/"/);
        });
    });

    describe('Runtime theme toggle and persistence across HomeLayout mounting', () => {
        it('preserves saved dark mode when HomeLayout mounts and allows toggling to light mode and back', () => {
            localStorage.setItem('theme', 'dark');
            initializeTheme();
            expect(getResolvedTheme()).toBe('dark');
            expect(isDarkMode()).toBe(true);
            expect(document.documentElement.classList.contains('dark')).toBe(true);

            // Mount HomeLayout
            const view = render(HomeLayout, {
                children: createRawSnippet(() => ({
                    render: () => '<div>Test Child</div>',
                })),
            });

            // Theme must remain dark on the homepage!
            expect(getThemePreference()).toBe('dark');
            expect(getResolvedTheme()).toBe('dark');
            expect(isDarkMode()).toBe(true);
            expect(document.documentElement.classList.contains('dark')).toBe(true);

            // User clicks theme toggle
            toggleTheme();
            expect(getThemePreference()).toBe('light');
            expect(getResolvedTheme()).toBe('light');
            expect(isDarkMode()).toBe(false);
            expect(document.documentElement.classList.contains('dark')).toBe(false);
            expect(localStorage.getItem('theme')).toBe('light');

            // User clicks theme toggle again
            toggleTheme();
            expect(getThemePreference()).toBe('dark');
            expect(getResolvedTheme()).toBe('dark');
            expect(isDarkMode()).toBe(true);
            expect(document.documentElement.classList.contains('dark')).toBe(true);
            expect(localStorage.getItem('theme')).toBe('dark');

            view.unmount();
        });
    });
});
