// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import GovernanceDiscussionPanel from '../../resources/js/Components/admission-support/GovernanceDiscussionPanel.svelte';
import { focusGovernanceTarget } from '../../resources/js/lib/governanceRequests';

const program = { id: 3, code: 'GOV-A' };
const cycle = { id: 8, name: '2026–2027' };
const originalThread = { id: 31, subject: 'Review current cutoff', status: 'open', unread: false, unread_count: 0, message_count: 1, can_read: true };
const initialList = { threads: [originalThread], pagination: { current_page: 1, has_more: false }, can_send: true };

function historyResponse() {
    return {
        thread: originalThread,
        messages: [{ id: 501, sender: { name: 'Guidance' }, body: 'Prior discussion', created_at: '2026-10-05T10:00:00Z' }],
        pagination: { has_more: false },
        can_read: true,
        can_send: true,
        can_manage_state: false,
        unread_count: 0,
    };
}

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

describe('GovernanceDiscussionPanel same-scope refresh', () => {
    it('updates its list prop without dropping the open conversation or unsent reply', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify(historyResponse()), { status: 200 }));
        vi.stubGlobal('fetch', fetchMock);
        const view = render(GovernanceDiscussionPanel, { props: { program, cycle, topic: 'benchmark', initialList } });

        await fireEvent.click(screen.getByRole('button', { name: /Review current cutoff/ }));
        await waitFor(() => expect(screen.getByText('Prior discussion')).toBeTruthy());
        const reply = screen.getByLabelText('Reply') as HTMLTextAreaElement;
        await fireEvent.input(reply, { target: { value: 'Unsent reply stays here' } });

        await view.rerender({
            program,
            cycle,
            topic: 'benchmark',
            initialList: {
                ...initialList,
                threads: [{ ...originalThread, unread: true, unread_count: 2 }, { ...originalThread, id: 32, subject: 'New list entry' }],
            },
        });

        expect(screen.getByRole('heading', { name: 'Review current cutoff' })).toBeTruthy();
        expect(screen.getByText('Prior discussion')).toBeTruthy();
        expect((screen.getByLabelText('Reply') as HTMLTextAreaElement).value).toBe('Unsent reply stays here');
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('opens the copy disclosure and scrolls and focuses both copy and history targets', () => {
        document.body.innerHTML = '<details><summary>Copy note</summary><select id="copy-source"></select></details><section id="note-history" tabindex="-1"></section>';
        vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
            callback(0);
            return 0;
        });
        const copySource = document.getElementById('copy-source') as HTMLSelectElement;
        const noteHistory = document.getElementById('note-history') as HTMLElement;
        const copyScroll = vi.fn();
        const historyScroll = vi.fn();
        Object.defineProperty(copySource, 'scrollIntoView', { value: copyScroll });
        Object.defineProperty(noteHistory, 'scrollIntoView', { value: historyScroll });

        focusGovernanceTarget('copy-source');
        expect((copySource.closest('details') as HTMLDetailsElement).open).toBe(true);
        expect(document.activeElement).toBe(copySource);
        expect(copyScroll).toHaveBeenCalledWith({ block: 'nearest' });

        focusGovernanceTarget('note-history');
        expect(document.activeElement).toBe(noteHistory);
        expect(historyScroll).toHaveBeenCalledWith({ block: 'nearest' });
    });
});
