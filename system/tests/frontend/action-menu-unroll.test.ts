// @vitest-environment jsdom
import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, render, screen } from '@testing-library/svelte';
import ActionMenuHarness from './fixtures/ActionMenuHarness.svelte';

afterEach(() => {
    cleanup();
});

describe('action menu unroll pattern for mobile', () => {
    it('renders trigger buttons and hides overflow items in standard mode', () => {
        render(ActionMenuHarness, { inMenu: false });

        // Trigger buttons exist
        const moreButtons = screen.getAllByRole('button', { name: /more actions/i });
        expect(moreButtons.length).toBe(2);

        // Sub-items are hidden in closed dropdowns
        expect(screen.queryByText('Applicant Waitlist')).toBeNull();
        expect(screen.queryByText('Edit')).toBeNull();
        expect(screen.queryByText('Delete')).toBeNull();
    });

    it('unrolls items directly without nested three-dot triggers when wrapped in ActionMenuProvider', () => {
        render(ActionMenuHarness, { inMenu: true });

        // No three-dot trigger buttons should exist
        const moreButtons = screen.queryAllByRole('button', { name: /more actions/i });
        expect(moreButtons.length).toBe(0);

        // ActionDropdown items are rendered directly as flat menu items
        expect(screen.getByText('Applicant Waitlist')).toBeDefined();
        expect(screen.getByText('Second Action')).toBeDefined();

        // EntityRowActions items are rendered directly as flat menu items
        expect(screen.getByText('Primary')).toBeDefined();
        expect(screen.getByText('Edit')).toBeDefined();
        expect(screen.getByText('Delete')).toBeDefined();
    });
});
