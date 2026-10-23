// @vitest-environment jsdom
import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';
import ImportWalkthrough from '../../resources/js/Components/Ingestion/ImportWalkthrough.svelte';

afterEach(() => {
    cleanup();
    document.body.replaceChildren();
    vi.unstubAllGlobals();
});

it('opens and dismisses inline guidance without discarding the current spreadsheet draft or trapping focus in a second dialog', async () => {
    vi.stubGlobal(
        'fetch',
        vi
            .fn()
            .mockResolvedValue({
                ok: true,
                json: async () => ({ emotions: {} }),
            }),
    );
    vi.stubGlobal(
        'matchMedia',
        vi.fn().mockReturnValue({
            matches: true,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        }),
    );
    const draftInput = document.createElement('input');
    draftInput.setAttribute('aria-label', 'Existing spreadsheet draft');
    draftInput.value = 'retained-applicants.xlsx';
    document.body.append(draftInput);
    render(ImportWalkthrough, {
        props: {
            context: {
                builderOpen: true,
                hasTemplateSchema: false,
                activePreset: 'scores_only',
            },
        },
    });
    const opener = screen.getByRole('button', { name: 'Guide me' });
    await fireEvent.click(opener);
    await waitFor(() =>
        expect(document.activeElement).toBe(screen.getByRole('heading')),
    );
    expect(screen.queryByRole('dialog')).toBeNull();
    await fireEvent.click(screen.getByRole('button', { name: 'Next' }));
    await fireEvent.click(
        screen.getByRole('button', { name: 'Close import guide' }),
    );
    await waitFor(() => expect(document.activeElement).toBe(opener));
    expect(draftInput.value).toBe('retained-applicants.xlsx');
    expect(opener.getAttribute('aria-expanded')).toBe('false');
});
