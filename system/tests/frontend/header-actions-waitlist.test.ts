// @vitest-environment jsdom
import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, render, screen } from '@testing-library/svelte';
import HeaderActionsWaitlistHarness from './fixtures/HeaderActionsWaitlistHarness.svelte';

afterEach(() => {
    cleanup();
});

describe('HeaderActions conditional waitlist badge promotion', () => {
    it('promotes waitlist button with badge when count > 0', () => {
        render(HeaderActionsWaitlistHarness, { waitlistCount: 7 });

        // Primary action is present in both desktop and mobile layouts
        expect(screen.getAllByText('Create session').length).toBe(2);

        // Waitlist alert action is rendered with numeric badge in both desktop and mobile
        const waitlistLinks = screen.getAllByTestId('waitlist-alert-button');
        expect(waitlistLinks.length).toBe(2);
        expect(screen.getAllByText('7').length).toBe(2);

        // Secondary action is present
        expect(screen.getAllByText('Auto-Distribute').length).toBeGreaterThan(0);

        // Overflow dropdown is NOT present when count > 0
        expect(screen.queryByTestId('overflow-dropdown')).toBeNull();
    });

    it('tucks waitlist into overflow when count is 0', () => {
        render(HeaderActionsWaitlistHarness, { waitlistCount: 0 });

        // Primary action is present in both desktop and mobile layouts
        expect(screen.getAllByText('Create session').length).toBe(2);

        // Waitlist alert action button is NOT rendered
        expect(screen.queryByTestId('waitlist-alert-button')).toBeNull();

        // Overflow dropdown IS present
        expect(screen.getAllByTestId('overflow-dropdown').length).toBeGreaterThan(0);
    });
});
