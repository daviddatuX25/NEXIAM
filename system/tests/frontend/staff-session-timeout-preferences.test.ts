// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import PreferencesMenu from '../../resources/js/Components/kit/PreferencesMenu.svelte';

const timeoutOptionNames = [
    '15 min — Standard (Recommended for Shared Desks)',
    '30 min — Extended',
    '60 min — Desk Work',
    '8 hours — Full Shift (Dedicated Desks)',
];

beforeEach(() => {
    localStorage.clear();
});

afterEach(() => {
    cleanup();
});

async function openPreferences(): Promise<void> {
    await fireEvent.click(
        screen.getByRole('button', {
            name: 'Accessibility and display preferences',
        }),
    );
    await screen.findByRole('button', { name: 'Dark', exact: true });
}

describe('staff workstation timeout preferences', () => {
    it('shows no timeout controls unless the staff surface opts in', async () => {
        const publicMenu = render(PreferencesMenu);
        await openPreferences();
        expect(screen.queryByText('Workstation Idle Timeout')).toBeNull();
        for (const name of timeoutOptionNames) {
            expect(screen.queryByRole('button', { name, exact: true })).toBeNull();
        }

        publicMenu.unmount();
        render(PreferencesMenu, { props: { isStaff: true } });
        await openPreferences();
        expect(screen.getByText('Workstation Idle Timeout')).toBeDefined();
        for (const name of timeoutOptionNames) {
            expect(screen.getByRole('button', { name, exact: true })).toBeDefined();
        }
    });

    it('loads the saved choice and persists and activates a new choice', async () => {
        localStorage.setItem('nexiam.workstation_timeout_preset', '60');
        render(PreferencesMenu, { props: { isStaff: true } });
        await openPreferences();

        const deskWorkOption = screen.getByRole('button', {
            name: timeoutOptionNames[2],
            exact: true,
        });
        expect(deskWorkOption.getAttribute('aria-pressed')).toBe('true');

        const extendedOption = screen.getByRole('button', {
            name: timeoutOptionNames[1],
            exact: true,
        });
        await fireEvent.click(extendedOption);

        expect(localStorage.getItem('nexiam.workstation_timeout_preset')).toBe('30');
        expect(extendedOption.getAttribute('aria-pressed')).toBe('true');
        expect(deskWorkOption.getAttribute('aria-pressed')).toBe('false');
    });

    it('selects 8 hours and reacts to external workstation timeout change events', async () => {
        render(PreferencesMenu, { props: { isStaff: true } });
        await openPreferences();

        const shiftOption = screen.getByRole('button', {
            name: timeoutOptionNames[3],
            exact: true,
        });
        expect(shiftOption.getAttribute('aria-pressed')).toBe('false');

        await fireEvent.click(shiftOption);
        expect(localStorage.getItem('nexiam.workstation_timeout_preset')).toBe('480');
        expect(shiftOption.getAttribute('aria-pressed')).toBe('true');

        // Simulate event dispatched from IdleTimeoutGuard extension
        window.dispatchEvent(
            new CustomEvent('nexiam:workstation_timeout_changed', { detail: 15 }),
        );
        await tick();
        expect(shiftOption.getAttribute('aria-pressed')).toBe('false');
        expect(
            screen
                .getByRole('button', { name: timeoutOptionNames[0], exact: true })
                .getAttribute('aria-pressed'),
        ).toBe('true');
    });
});
