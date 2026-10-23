// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type * as Inertia from '@inertiajs/svelte';
import PreferencesMenu from '../../resources/js/Components/kit/PreferencesMenu.svelte';
import HomeLayout from '../../resources/js/Layouts/HomeLayout.svelte';
import { createRawSnippet } from 'svelte';
import { resetScale, setScale } from '../../resources/js/lib/app-scale.svelte';
import {
    getThemePreference,
    setThemePreference,
} from '../../resources/js/lib/theme.svelte';
import { sound } from '../../resources/js/lib/sound.js';

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<typeof Inertia>();
    return {
        ...actual,
        usePage: () => ({ props: { auth: { user: null } }, url: '/' }),
    };
});

beforeEach(() => {
    localStorage.clear();
    resetScale();
    setThemePreference('light');
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
    cleanup();
    vi.unstubAllGlobals();
});

async function openPreferences() {
    await fireEvent.click(
        screen.getByRole('button', {
            name: 'Accessibility and display preferences',
        }),
    );
    await screen.findByRole('button', { name: 'Dark', exact: true });
}

describe('staff display preferences', () => {
    it('changes shared theme and scale on the staff surface', async () => {
        render(PreferencesMenu);
        await openPreferences();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Dark', exact: true }),
        );
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem('theme')).toBe('dark');
        await fireEvent.click(
            screen.getByRole('button', { name: 'Increase interface scale' }),
        );
        expect(document.documentElement.style.fontSize).toBe('16.1px');
        expect(localStorage.getItem('app_scale')).toBe('115');
        await fireEvent.click(
            screen.getByRole('button', { name: 'Reset interface scale' }),
        );
        expect(localStorage.getItem('app_scale')).toBe('100');
    });

    it('retains staff sound and browser-alert controls on the staff surface', async () => {
        render(PreferencesMenu);
        await openPreferences();
        expect(
            screen.getByRole('switch', { name: 'Interface sounds' }),
        ).toBeDefined();
        expect(
            screen.getByText('Browser alert when tab is hidden'),
        ).toBeDefined();
    });
});

describe('homepage preference isolation', () => {
    it('enables sound and normal scale only while mounted without overwriting staff settings', () => {
        setThemePreference('dark');
        setScale(125);
        sound.disable();
        const view = render(HomeLayout, {
            children: createRawSnippet(() => ({
                render: () => '<div>Homepage</div>',
            })),
        });

        expect(getThemePreference()).toBe('dark');
        expect(document.documentElement.style.fontSize).toBe('14px');
        expect(sound.isEnabled()).toBe(true);
        expect(sound.ctx).toBeNull();
        expect(localStorage.getItem('theme')).toBe('dark');
        expect(localStorage.getItem('app_scale')).toBe('125');
        expect(localStorage.getItem('snd_sine_enabled')).toBe('false');

        view.unmount();
        expect(getThemePreference()).toBe('dark');
        expect(document.documentElement.style.fontSize).toBe('17.5px');
        expect(sound.isEnabled()).toBe(false);
    });
});
