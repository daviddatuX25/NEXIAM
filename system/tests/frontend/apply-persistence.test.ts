// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { tick } from 'svelte';
import ApplyPersistenceHarness from './fixtures/ApplyPersistenceHarness.svelte';
import { loadDraft, saveDraft } from '../../resources/js/lib/draft-storage';

beforeEach(() => {
    window.sessionStorage.clear();
    vi.useFakeTimers();
});

afterEach(() => {
    cleanup();
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('admission application persistence', () => {
    it('saves draft fields without terms acceptance and restores the birthdate input', async () => {
        const view = render(ApplyPersistenceHarness);

        await fireEvent.input(screen.getByRole('textbox', { name: 'First name' }), {
            target: { value: 'Ada' },
        });
        await fireEvent.input(screen.getByRole('textbox', { name: 'Birthdate' }), {
            target: { value: '02-29-2000' },
        });
        await fireEvent.click(screen.getByRole('checkbox', { name: 'Accept terms' }));
        await tick();
        await vi.advanceTimersByTimeAsync(500);

        expect(loadDraft('admission_apply', {
            cycle_id: 'cycle-1',
            window_id: 'window-1',
            policy_digest: 'policy-1',
        })).toEqual({
            first_name: 'Ada',
            birthdate_input: '02-29-2000',
        });

        view.unmount();
        render(ApplyPersistenceHarness);

        expect((screen.getByRole('textbox', { name: 'First name' }) as HTMLInputElement).value).toBe('Ada');
        expect((screen.getByRole('textbox', { name: 'Birthdate' }) as HTMLInputElement).value).toBe('02-29-2000');
        expect((screen.getByRole('checkbox', { name: 'Accept terms' }) as HTMLInputElement).checked).toBe(false);
    });
    it('uses the opaque-link email only when no recovered draft overrides it', async () => {
        const context = {
            cycle_id: 'cycle-1',
            window_id: 'window-1',
            policy_digest: 'policy-1',
        };
        saveDraft('admission_apply', context, { email: 'saved-draft@example.edu' });

        render(ApplyPersistenceHarness, {
            props: { prefilledEmail: 'mail-link@example.edu' },
        });

        expect(
            (screen.getByRole('textbox', { name: 'Email' }) as HTMLInputElement).value,
        ).toBe('saved-draft@example.edu');
    });

    it('keeps the visitor-edited email when a later prefill prop changes', async () => {
        const view = render(ApplyPersistenceHarness, {
            props: { prefilledEmail: 'mail-link@example.edu' },
        });
        const email = screen.getByRole('textbox', { name: 'Email' });

        expect((email as HTMLInputElement).value).toBe('mail-link@example.edu');
        await fireEvent.input(email, {
            target: { value: 'visitor-edit@example.edu' },
        });
        await view.rerender({
            prefilledEmail: 'new-link@example.edu',
        });
        expect((email as HTMLInputElement).value).toBe('visitor-edit@example.edu');
    });


    it('clears the saved draft when clearSavedDraft is invoked', async () => {
        render(ApplyPersistenceHarness);
        await fireEvent.input(screen.getByRole('textbox', { name: 'First name' }), {
            target: { value: 'Ada' },
        });
        await tick();
        await vi.advanceTimersByTimeAsync(500);

        expect(window.sessionStorage.getItem('nexiam:draft:v1:admission_apply')).not.toBeNull();

        await fireEvent.click(screen.getByRole('button', { name: 'Clear draft' }));

        expect(window.sessionStorage.getItem('nexiam:draft:v1:admission_apply')).toBeNull();
    });
});
