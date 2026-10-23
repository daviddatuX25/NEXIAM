// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import LocationPrivacyDrawer from '../../resources/js/Components/Wayfinding/LocationPrivacyDrawer.svelte';

beforeEach(() => {
    if (typeof HTMLElement.prototype.animate !== 'function') {
        HTMLElement.prototype.animate = vi.fn().mockReturnValue({
            finished: Promise.resolve(),
            cancel: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        });
    }
});

describe('LocationPrivacyDrawer', () => {
    afterEach(() => {
        cleanup();
        localStorage.clear();
    });

    it('renders the zero-tracking notice and venue name when open', () => {
        render(LocationPrivacyDrawer, {
            open: true,
            targetVenueName: 'ITBR-106',
            onAccept: vi.fn(),
            onDecline: vi.fn(),
        });

        expect(screen.getByText(/Find ITBR-106 with Campus Compass/i)).toBeTruthy();
        expect(screen.getByText(/ZERO SERVER TRACKING/i)).toBeTruthy();
        expect(screen.getByRole('button', { name: /Enable Compass & GPS/i })).toBeTruthy();
        expect(screen.getByRole('button', { name: /Browse Manually/i })).toBeTruthy();
    });

    it('invokes onAccept and persists granted choice to localStorage on accept', async () => {
        const onAccept = vi.fn();
        const onDecline = vi.fn();

        render(LocationPrivacyDrawer, {
            open: true,
            targetVenueName: 'ITBR-106',
            onAccept,
            onDecline,
        });

        const acceptBtn = screen.getByRole('button', { name: /Enable Compass & GPS/i });
        await fireEvent.click(acceptBtn);

        expect(onAccept).toHaveBeenCalledOnce();
        expect(onDecline).not.toHaveBeenCalled();
        expect(localStorage.getItem('nexiam_wayfinding_consent_v1')).toBe('granted');
    });

    it('invokes onDecline and persists manual choice to localStorage on decline', async () => {
        const onAccept = vi.fn();
        const onDecline = vi.fn();

        render(LocationPrivacyDrawer, {
            open: true,
            targetVenueName: 'ITBR-106',
            onAccept,
            onDecline,
        });

        const declineBtn = screen.getByRole('button', { name: /Browse Manually/i });
        await fireEvent.click(declineBtn);

        expect(onDecline).toHaveBeenCalledOnce();
        expect(onAccept).not.toHaveBeenCalled();
        expect(localStorage.getItem('nexiam_wayfinding_consent_v1')).toBe('manual');
    });
});
