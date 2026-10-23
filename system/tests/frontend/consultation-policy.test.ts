// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mockRouterVisit = vi.fn();
const mockRouterPost = vi.fn();
const mockRouterOn = vi.fn(() => () => {});

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = (actual.router as Record<string, unknown>) ?? {};

    return {
        ...actual,
        usePage: () => ({ props: { flash: {}, errors: {} } }),
        router: {
            ...actualRouter,
            on: (...args: unknown[]) => mockRouterOn(...args),
            visit: (...args: unknown[]) => mockRouterVisit(...args),
            post: (...args: unknown[]) => mockRouterPost(...args),
        },
    };
});

import ConsultationPolicy from '../../resources/js/Pages/Setup/ConsultationPolicy.svelte';

const cycle = { id: 8, label: '2026–2027', is_active: true };

function props(policy = {}, returnTo = null) {
    return {
        academic_cycles: [cycle],
        selected_cycle_id: 8,
        return_to: returnTo,
        policy: {
            normal_method: 'online',
            allow_online_exceptions: false,
            version: 2,
            media_ready: true,
            media_unavailable_reason: null,
            ...policy,
        },
    };
}

beforeEach(() => {
    mockRouterOn.mockClear();
    mockRouterPost.mockReset();
    mockRouterVisit.mockReset();
});
afterEach(() => cleanup());

describe('consultation policy setup', () => {
    it('defaults to Online and saves the exact policy contract with the expected version', async () => {
        const view = render(ConsultationPolicy, props());

        expect((view.getByRole('radio', { name: /Online/ }) as HTMLInputElement).checked).toBe(true);
        expect(view.queryByRole('checkbox', { name: /Allow online exceptions/ })).toBeNull();
        await fireEvent.click(view.getByRole('button', { name: 'Save' }));

        expect(mockRouterPost).toHaveBeenCalledWith('/setup/consultation-policy', {
            academic_cycle_id: 8,
            normal_method: 'online',
            allow_online_exceptions: false,
            expected_version: 2,
        }, expect.objectContaining({ preserveScroll: true }));
    });
    it('returns to the server-provided consultation destination and guards unsaved form edits', async () => {
        const confirmDiscard = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const view = render(ConsultationPolicy, props({}, '/consultation'));

        expect(view.getByRole('button', { name: 'Return to Consultation' })).not.toBeNull();
        await fireEvent.click(view.getByRole('radio', { name: /Face-to-face/ }));
        await fireEvent.click(view.getByRole('button', { name: 'Return to Consultation' }));
        expect(confirmDiscard).toHaveBeenCalledTimes(1);
        expect(mockRouterVisit).not.toHaveBeenCalled();
        expect(view.getByRole('alert').textContent).toContain('Unsaved policy changes were kept');

        confirmDiscard.mockReturnValue(true);
        await fireEvent.click(view.getByRole('button', { name: 'Return to Consultation' }));
        expect(mockRouterVisit).toHaveBeenCalledWith('/consultation', expect.objectContaining({ onFinish: expect.any(Function) }));
        confirmDiscard.mockRestore();
    });


    it('offers the optional exception checkbox only for F2F and submits it for the individual-start policy', async () => {
        const view = render(ConsultationPolicy, props());

        await fireEvent.click(view.getByRole('radio', { name: /Face-to-face/ }));
        const exceptions = view.getByRole('checkbox', { name: /Allow online exceptions/ }) as HTMLInputElement;
        expect(exceptions.checked).toBe(false);
        await fireEvent.click(exceptions);
        await fireEvent.click(view.getByRole('button', { name: 'Save' }));

        expect(mockRouterPost).toHaveBeenCalledWith('/setup/consultation-policy', {
            academic_cycle_id: 8,
            normal_method: 'f2f',
            allow_online_exceptions: true,
            expected_version: 2,
        }, expect.objectContaining({ preserveScroll: true }));
        expect(view.getByText(/never converts bookings or changes results/i)).not.toBeNull();
        expect(view.queryByRole('textbox')).toBeNull();
    });

    it('clears drafts when authoritative policy props change and hides the exception control on Online', async () => {
        const view = render(ConsultationPolicy, props());
        await fireEvent.click(view.getByRole('radio', { name: /Face-to-face/ }));
        await fireEvent.click(view.getByRole('checkbox', { name: /Allow online exceptions/ }));

        await view.rerender(props({ normal_method: 'online', allow_online_exceptions: false, version: 3 }));
        await waitFor(() => expect((view.getByRole('radio', { name: /Online/ }) as HTMLInputElement).checked).toBe(true));
        expect(view.queryByRole('checkbox', { name: /Allow online exceptions/ })).toBeNull();

        await fireEvent.click(view.getByRole('button', { name: 'Save' }));
        expect(mockRouterPost).toHaveBeenLastCalledWith('/setup/consultation-policy', {
            academic_cycle_id: 8,
            normal_method: 'online',
            allow_online_exceptions: false,
            expected_version: 3,
        }, expect.objectContaining({ preserveScroll: true }));
    });


    it('keeps the save pending until completion and shows save errors and success feedback', async () => {
        const view = render(ConsultationPolicy, props());
        await fireEvent.click(view.getByRole('button', { name: 'Save' }));

        const [, , callbacks] = mockRouterPost.mock.calls[0];
        const saveButton = view.getByRole('button', { name: 'Saving…' }) as HTMLButtonElement;
        expect(saveButton.disabled).toBe(true);

        callbacks.onError({ normal_method: 'The policy changed. Reload and try again.' });
        await waitFor(() => expect(view.getByRole('alert').textContent).toContain('The policy changed.'));
        expect((view.getByRole('button', { name: 'Saving…' }) as HTMLButtonElement).disabled).toBe(true);

        callbacks.onFinish();
        await waitFor(() => expect(view.getByRole('button', { name: 'Save' })).not.toBeNull());

        await fireEvent.click(view.getByRole('button', { name: 'Save' }));
        mockRouterPost.mock.calls[1][2].onSuccess();
        await waitFor(() => expect(view.getByRole('status').textContent).toContain('Consultation policy saved.'));
    });
    it('keeps policy editing available while online consultation is marked beta and unavailable', () => {
        const view = render(ConsultationPolicy, props({
            media_ready: false,
            media_unavailable_reason: 'Conference service is not configured.',
        }));

        expect(view.getByRole('button', { name: 'Save' })).not.toBeNull();
        expect((view.getByRole('radio', { name: /Online/ }) as HTMLInputElement).checked).toBe(true);
    });
});
