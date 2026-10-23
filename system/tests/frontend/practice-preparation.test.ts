// @vitest-environment jsdom
import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Panel from '../../resources/js/Pages/Foundation/PracticePreparationPanel.svelte';
import DemoGuide from '../../resources/js/Pages/Foundation/DemoGuide.svelte';
import Facilitator from '../../resources/js/Pages/Foundation/ResearchFacilitatorGuide.svelte';

function preparation(scenario = 'student', label = 'Student practice', purpose = 'research') {
    return {
        purpose, scenario,
        scenarios: [{ key: 'student', label: 'Student' }, { key: 'guidance', label: 'Guidance' }],
        context: { institution: 'Practice institution', cycle: 'Current academic year' },
        sets: [], selected_set: null,
        prerequisites: [{ key: 'paper', label: 'Physical output', status: 'unverified', reason: 'No physical evidence recorded.' }],
        actions: [{ key: 'prepare', label: `Prepare ${label}`, enabled: true, reason: '' }],
        preserved: ['Institutional quotas', 'Historic records'],
        endpoints: { index: '/demo/preparation', preview: '/demo/preparation/preview', confirm_template: '/demo/preparation/operations/{operation}/confirm' },
    };
}
function reviewed() {
    return {
        operation: { id: 'operation-fixed', action: 'prepare', expires_at: '2099-01-01T00:00:00Z', confirm_url: '/demo/preparation/operations/operation-fixed/confirm' },
        impact: { creates: ['Fresh student cases'], replaces: [], preserves: ['Historic records'] },
        prerequisites: [], can_confirm: true, reason: null,
    };
}
function json(value: unknown, status = 200) {
    return new Response(JSON.stringify(value), { status, headers: { 'content-type': 'application/json' } });
}
function deferred() {
    let resolve!: (value: Response) => void;
    const promise = new Promise<Response>((done) => { resolve = done; });
    return { promise, resolve };
}

beforeEach(() => {
    document.head.innerHTML = '<meta name="csrf-token" content="shared-csrf">';
    vi.stubGlobal('ResizeObserver', class { observe() {} unobserve() {} disconnect() {} });
    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => { queueMicrotask(() => callback(0)); return 1; });
    window.matchMedia = vi.fn().mockReturnValue({ matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn() });
});
afterEach(() => { cleanup(); vi.unstubAllGlobals(); vi.restoreAllMocks(); });

describe('protected practice preparation', () => {
    it('does not load or expose controls to a guest, or fetch before the operator opens it', async () => {
        const fetcher = vi.fn().mockResolvedValue(json(preparation()));
        vi.stubGlobal('fetch', fetcher);
        const view = render(Panel, { purpose: 'research', scenario: 'student', active: true, can_control: false });
        await tick();
        expect(view.queryByRole('region', { name: 'Practice preparation' })).toBeNull();
        expect(fetcher).not.toHaveBeenCalled();
        await view.rerender({ purpose: 'research', scenario: 'student', active: false, can_control: true });
        expect(fetcher).not.toHaveBeenCalled();
        await view.rerender({ purpose: 'research', scenario: 'student', active: true, can_control: true });
        await view.findByRole('button', { name: 'Prepare Student practice' });
        expect(fetcher).toHaveBeenCalledTimes(1);
    });

    it('discards a late response when the researcher changes roles', async () => {
        const first = deferred();
        const second = deferred();
        const fetcher = vi.fn().mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise);
        vi.stubGlobal('fetch', fetcher);
        const view = render(Panel, { purpose: 'research', scenario: 'student', can_control: true });
        await waitFor(() => expect(fetcher).toHaveBeenCalledTimes(1));
        await view.rerender({ purpose: 'research', scenario: 'guidance', can_control: true });
        await waitFor(() => expect(fetcher).toHaveBeenCalledTimes(2));
        second.resolve(json(preparation('guidance', 'Guidance practice')));
        await view.findByRole('button', { name: 'Prepare Guidance practice' });
        first.resolve(json(preparation('student', 'Old student response')));
        await tick(); await tick();
        expect(view.queryByRole('button', { name: 'Prepare Old student response' })).toBeNull();
        expect(new URL(fetcher.mock.calls[1][0], window.location.origin).searchParams.get('scenario')).toBe('guidance');
    });

    it('waits for fresh material checks before reviewing the selected binding', async () => {
        const options = [{ id: 7, label: 'Sheet and booklet seven' }, { id: 9, label: 'Sheet and booklet nine' }];
        const checked = deferred();
        const initial = { ...preparation(), materials: { booklets: options, selected_booklet_version_id: null }, actions: [{ key: 'prepare', label: 'Prepare Student practice', enabled: false, reason: 'Choose a binding.' }] };
        const selected = { ...preparation(), materials: { booklets: options, selected_booklet_version_id: 9 } };
        const fetcher = vi.fn().mockResolvedValueOnce(json(initial)).mockReturnValueOnce(checked.promise).mockResolvedValueOnce(json(reviewed()));
        vi.stubGlobal('fetch', fetcher);
        const view = render(Panel, { purpose: 'research', scenario: 'student', can_control: true });
        const choice = await view.findByLabelText('Exam material binding');
        await fireEvent.change(choice, { target: { value: '9' } });
        expect((view.getByRole('button', { name: 'Prepare Student practice' }) as HTMLButtonElement).disabled).toBe(true);
        checked.resolve(json(selected));
        await waitFor(() => expect((view.getByRole('button', { name: 'Prepare Student practice' }) as HTMLButtonElement).disabled).toBe(false));
        await fireEvent.click(view.getByRole('button', { name: 'Prepare Student practice' }));
        await view.findByRole('dialog');
        expect(JSON.parse(fetcher.mock.calls[2][1].body).booklet_version_id).toBe(9);
        await fireEvent.click(view.getByRole('button', { name: 'Cancel', exact: true }));
        expect(fetcher).toHaveBeenCalledTimes(3);
    });

    it('cancels impact review without a confirmation request', async () => {
        const fetcher = vi.fn().mockResolvedValueOnce(json(preparation())).mockResolvedValueOnce(json(reviewed()));
        vi.stubGlobal('fetch', fetcher);
        const view = render(Panel, { purpose: 'research', scenario: 'student', can_control: true });
        await fireEvent.click(await view.findByRole('button', { name: 'Prepare Student practice' }));
        const dialog = await view.findByRole('dialog');
        await waitFor(() => expect(document.activeElement).toBe(dialog));
        expect(view.getByText('Fresh student cases')).toBeTruthy();
        await fireEvent.click(view.getByRole('button', { name: 'Cancel', exact: true }));
        await waitFor(() => expect(view.queryByRole('dialog')).toBeNull());
        expect(fetcher).toHaveBeenCalledTimes(2);
        expect(JSON.parse(fetcher.mock.calls[1][1].body)).toEqual({ purpose: 'research', scenario: 'student', action: 'prepare' });
    });

    it('retries an unknown confirmation outcome using only the original operation URL', async () => {
        const committed = {
            ...preparation(),
            sets: [{ id: 'set-result', label: 'Mara practice', scenario: 'student', purpose: 'research', status: 'active', revision: 1 }],
            selected_set: { id: 'set-result', label: 'Mara practice', scenario: 'student', purpose: 'research', status: 'active', revision: 1, cases: [], actors: [], sessions: [], documents: [], paired_exchange: { status: 'unverified', reason: 'Not checked' }, deliveries: [] },
        };
        const fetcher = vi.fn().mockResolvedValueOnce(json(preparation())).mockResolvedValueOnce(json(reviewed())).mockRejectedValueOnce(new TypeError('Network response lost')).mockResolvedValueOnce(json({ message: 'Fresh practice created.', set_id: 'set-result', result: { created: 1 }, preparation: committed }));
        vi.stubGlobal('fetch', fetcher);
        const view = render(Panel, { purpose: 'research', scenario: 'student', can_control: true });
        await fireEvent.click(await view.findByRole('button', { name: 'Prepare Student practice' }));
        await fireEvent.click(await view.findByRole('button', { name: 'Confirm prepare' }));
        await fireEvent.click(await view.findByRole('button', { name: 'Retry same operation' }));
        await view.findByText('Fresh practice created.');
        expect(fetcher.mock.calls[2][0]).toBe('/demo/preparation/operations/operation-fixed/confirm');
        expect(fetcher.mock.calls[3][0]).toBe(fetcher.mock.calls[2][0]);
        expect(fetcher.mock.calls[3][1].body).toBe('{}');
        expect(view.getByRole('heading', { name: 'Mara practice' })).toBeTruthy();
    });

    it('retains the selected reader details and stops confirmation after a revision conflict', async () => {
        const fetcher = vi.fn().mockResolvedValueOnce(json(preparation())).mockResolvedValueOnce(json(reviewed())).mockResolvedValueOnce(json({ message: 'The selected set changed. Refresh details.' }, 409));
        vi.stubGlobal('fetch', fetcher);
        const view = render(Panel, { purpose: 'research', scenario: 'student', can_control: true });
        await fireEvent.click(await view.findByRole('button', { name: 'Prepare Student practice' }));
        await fireEvent.click(await view.findByRole('button', { name: 'Confirm prepare' }));
        await view.findByText('The selected set changed. Refresh details.');
        expect((view.getByRole('button', { name: 'Confirm prepare' }) as HTMLButtonElement).disabled).toBe(true);
        expect(view.queryByRole('button', { name: 'Retry same operation' })).toBeNull();
        await fireEvent.click(view.getByRole('button', { name: 'Cancel', exact: true }));
        expect(view.getByRole('button', { name: 'Prepare Student practice' })).toBeTruthy();
        expect(fetcher).toHaveBeenCalledTimes(3);
    });

    it('ignores a cancelled in-flight preview even when its response arrives later', async () => {
        const late = deferred();
        const fetcher = vi.fn().mockResolvedValueOnce(json(preparation())).mockReturnValueOnce(late.promise);
        vi.stubGlobal('fetch', fetcher);
        const view = render(Panel, { purpose: 'research', scenario: 'student', can_control: true });
        await fireEvent.click(await view.findByRole('button', { name: 'Prepare Student practice' }));
        await fireEvent.click(view.getByRole('button', { name: 'Cancel review' }));
        late.resolve(json(reviewed()));
        await tick(); await tick();
        expect(view.queryByRole('dialog')).toBeNull();
        expect(fetcher).toHaveBeenCalledTimes(2);
    });

    it.each(['replace', 'retire', 'pair', 'time', 'reminder'])('reviews and cancels the exact owned %s action', async (action) => {
        const selectedSet = {
            id: 'owned-set', label: 'Mara practice', scenario: 'student', purpose: 'research', status: 'active', revision: 3,
            cases: [], actors: [], documents: [], deliveries: [],
            paired_exchange: { status: 'missing', reason: 'Review current case parties' },
            sessions: [{ id: 'session-owned', label: 'Morning session', status: 'published', starts_at: null, ends_at: null }],
        };
        const initial = {
            ...preparation(), selected_set: selectedSet,
            sets: [{ id: selectedSet.id, label: selectedSet.label, scenario: selectedSet.scenario, purpose: selectedSet.purpose, status: selectedSet.status, revision: selectedSet.revision }],
            actions: [{ key: action, label: `Review ${action}`, enabled: true, reason: '' }],
        };
        const preview = reviewed();
        preview.operation.action = action;
        const fetcher = vi.fn().mockResolvedValue(json(preview));
        vi.stubGlobal('fetch', fetcher);
        const view = render(Panel, { purpose: 'research', scenario: 'student', can_control: true, initial });
        await tick();
        if (action === 'pair') await fireEvent.click(view.getByText('Paired case exchange'));
        if (action === 'time' || action === 'reminder') await fireEvent.click(view.getByText('Session timing and reminders'));
        await fireEvent.click(view.getByRole('button', { name: `Review ${action}`, exact: true }));
        await view.findByRole('button', { name: `Confirm ${action}` });
        const body = JSON.parse(fetcher.mock.calls[0][1].body);
        expect(body).toEqual({
            purpose: 'research', scenario: 'student', action, set_id: 'owned-set',
            ...(action === 'time' ? { session_id: 'session-owned', preset: '1h-reminder' } : {}),
            ...(action === 'reminder' ? { session_id: 'session-owned', window: '1h' } : {}),
        });
        await fireEvent.click(view.getByRole('button', { name: 'Cancel', exact: true }));
        expect(fetcher).toHaveBeenCalledTimes(1);
    });

    it('keeps one bounded demo activity and resets its selection on workflow change', async () => {
        const step = (key: string, title: string) => ({ key, title, actor: 'Registrar', before: 'Check scope', actions: ['Review the record'], expected: 'Record reviewed', next_actor: 'Guidance' });
        const view = render(DemoGuide, { can_control: false, workflows: [{ key: 'application-review', title: 'Applications', steps: [step('first', 'Review application'), step('second', 'Review correction')] }, { key: 'enrollment-verification', title: 'Enrollment', steps: [step('handoff', 'Review eligibility')] }] });
        expect((view.getByRole('button', { name: 'Previous', exact: true }) as HTMLButtonElement).disabled).toBe(true);
        expect(view.getByRole('button', { name: 'Before', exact: true }).getAttribute('aria-pressed')).toBe('true');
        expect(view.queryByText('Review the record')).toBeNull();
        await fireEvent.click(view.getByRole('button', { name: 'Steps', exact: true }));
        expect(view.getByText('Review the record')).toBeTruthy();
        expect(view.queryByText('Record reviewed')).toBeNull();
        await fireEvent.click(view.getByRole('button', { name: 'After', exact: true }));
        expect(view.getByText('Record reviewed')).toBeTruthy();
        expect(view.queryByText('Review the record')).toBeNull();
        await fireEvent.click(view.getByRole('button', { name: 'Next', exact: true }));
        expect(view.getByRole('heading', { name: 'Review correction' })).toBeTruthy();
        await fireEvent.change(view.getByLabelText('Choose a workflow'), { target: { value: 'enrollment-verification' } });
        expect(view.getByRole('heading', { name: 'Review eligibility' })).toBeTruthy();
        expect(view.queryByRole('heading', { name: 'Review correction' })).toBeNull();
        expect((view.getByRole('button', { name: 'Previous', exact: true }) as HTMLButtonElement).disabled).toBe(true);
        expect((view.getByRole('button', { name: 'Next', exact: true }) as HTMLButtonElement).disabled).toBe(true);
        expect(view.queryByRole('button', { name: 'Prepare this workflow' })).toBeNull();
        expect(view.getByRole('button', { name: 'Before', exact: true }).getAttribute('aria-pressed')).toBe('true');
    });

    it('loads researcher preparation for the selected role without replacing its canonical reference', async () => {
        const fetcher = vi.fn().mockResolvedValue(json(preparation('guidance', 'Guidance practice')));
        vi.stubGlobal('fetch', fetcher);
        const view = render(Facilitator, { selected_role: 'guidance', can_control: true, facilitator: { preparation_markdown: 'Canonical briefing' } });
        const entry = view.getByRole('button', { name: 'Prepare research practice' });
        expect(fetcher).not.toHaveBeenCalled();
        await fireEvent.click(entry);
        await view.findByRole('button', { name: 'Prepare Guidance practice' });
        const url = new URL(fetcher.mock.calls[0][0], window.location.origin);
        expect(url.searchParams.get('purpose')).toBe('research');
        expect(url.searchParams.get('scenario')).toBe('guidance');
        expect(view.getByText('Canonical briefing')).toBeTruthy();
    });
});
