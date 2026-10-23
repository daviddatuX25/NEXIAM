// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';

const requests = vi.hoisted(() => ({ reload: vi.fn(), post: vi.fn() }));

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...await importOriginal<Record<string, unknown>>(),
    usePage: () => ({ props: { csrf_token: 'synthetic-test-token', errors: {}, flash: {} } }),
    router: { reload: requests.reload, post: requests.post },
}));
vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(anchor),
}));
vi.mock('@/Components/ResultSheet', () => ({
    PrintJobCard: () => null,
    PrintJobHistory: () => null,
    MarkPrintedDialog: () => null,
}));

import PrintBatch from '../../resources/js/Pages/Release/PrintBatch.svelte';

afterEach(async () => {
    cleanup();
    await waitFor(() => expect(document.body.style.overflow).not.toBe('hidden'));
    vi.unstubAllGlobals();
    requests.reload.mockReset();
    requests.post.mockReset();
});

it('prepares only explicitly confirmed eligible applicants while the grading batch remains open', async () => {
    const fetchRequest = vi.fn(async (_url: string, _options: RequestInit) => ({ ok: true }));
    vi.stubGlobal('fetch', fetchRequest);
    render(PrintBatch, {
        session: { exam_session_id: 7, code: 'EX-INDIVIDUAL', status: 'open' },
        can_prepare: true,
        template_present: true,
        applicants: [
            { attempt_id: 11, reference: 'AY26-00011', name: 'Complete Candidate', scoring_ready: true, document_ready: false, document_id: null, printed: false },
            { attempt_id: 12, reference: 'AY26-00012', name: 'Incomplete Candidate', scoring_ready: false, scoring_reason: 'A required score is missing.', document_ready: false, document_id: null, printed: false },
        ],
    });

    expect(screen.getAllByRole('button', { name: 'Prepare', exact: true })).toHaveLength(1);
    await fireEvent.click(screen.getByRole('button', { name: 'Prepare 1 eligible' }));
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(fetchRequest).not.toHaveBeenCalled();
    expect(requests.post).not.toHaveBeenCalled();
    await fireEvent.click(screen.getByRole('button', { name: 'Cancel', exact: true }));
    expect(fetchRequest).not.toHaveBeenCalled();
    expect(requests.post).not.toHaveBeenCalled();

    await fireEvent.click(screen.getByRole('button', { name: 'Prepare 1 eligible' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Prepare documents', exact: true }));
    await waitFor(() => expect(fetchRequest).toHaveBeenCalledTimes(1));
    expect(fetchRequest.mock.calls[0][0]).toBe('/release/11/prepare');
    expect(requests.post).not.toHaveBeenCalled();
});
