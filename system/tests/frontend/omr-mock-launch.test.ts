// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, beforeEach, expect, it, vi, type Mock, type MockInstance } from 'vitest';

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    return {
        ...actual,
        usePage: () => ({ props: { auth: { user: { id: 17, name: 'Test staff' } }, staff_navigation: [], errors: {} }, url: '/setup/exam-materials/omr-test' }),
        router: { on: () => () => {}, get: vi.fn(), post: vi.fn(), visit: vi.fn(), reload: vi.fn() },
    };
});

import OmrTest from '../../resources/js/Pages/Setup/OmrTest.svelte';

const manifestHash = 'a'.repeat(64);
const guide = {
    test_kit_version_id: 2,
    instance_count: 1,
    pattern_mode: 'guided_mix',
    pages: [{ page_no: 1, available: false, instances: [] }],
};
const context = {
    version_id: 2, revision: 1, code: 'SYN-MOCK', version_number: 2,
    template_version_id: 28, booklet_version_id: 3, return_url: '/setup/exam-materials/test-kits/1?version=2',
    answer_sheet: { version_id: 28, name: 'Synthetic sheet', page_count: 1 },
    booklet: { name: 'Synthetic booklet', version_id: 3, set_code: 'A' },
};
let fetchMock: Mock;
let popup: { opener: unknown; location: { replace: Mock }; close: Mock };
let openMock: MockInstance;
beforeEach(() => {
    window.history.replaceState({}, '', '/setup/exam-materials/omr-test?test_kit_version_id=2');
    vi.stubGlobal('ResizeObserver', class { observe() {} unobserve() {} disconnect() {} });
    popup = { opener: window, location: { replace: vi.fn() }, close: vi.fn() };
    openMock = vi.spyOn(window, 'open').mockReturnValue(popup as unknown as Window);
    fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
        if (String(url).includes('/mock-guide?')) {
            return { ok: true, json: async () => ({ mock_guide: guide, manifest_sha256: manifestHash, mock_config: { instance_count: 1, pattern_mode: 'guided_mix' } }) };
        }
        if (init?.method === 'POST' && !String(url).includes('/runs/')) {
            return { ok: true, json: async () => ({ context: { public_id: 'mock-run-1' }, recovery: {} }) };
        }
        if (String(url).includes('/runs/')) {
            const publicId = String(url).match(/\/runs\/([^/?]+)/)?.[1] ?? 'retained-run';
            const projection = {
                workbench_id: `diagnostic:${publicId}`,
                execution_kind: 'diagnostic',
                revision: 4,
                phase: 'capture',
                status: init?.method === 'POST' ? 'cancelled' : 'open',
                context: {
                    execution_kind: 'diagnostic',
                    public_id: publicId,
                    status: init?.method === 'POST' ? 'cancelled' : 'open',
                    diagnostic_purpose: 'mock_rehearsal',
                    mock_config: { instance_count: 1, pattern_mode: 'guided_mix' },
                },
                sheets: [{ id: 22, status: 'needs_review' }],
                evidence: { references: [], items: [], captures: [], redacted: true },
                recovery: { items: [], needs_attention: [], count: 0, outcome: init?.method === 'POST' ? { kind: 'success' } : null },
                completion: null,
                commands: init?.method === 'POST' ? [] : ['diagnostic_cancel'],
            };
            return new Response(JSON.stringify(projection), { headers: { 'Content-Type': 'application/json' } });
        }
        throw new Error(`Unexpected request ${url}`);
    });
    vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

function mount(overrides: Record<string, unknown> = {}) {
    return render(OmrTest, { props: { test_kit_context: { ...context, mock_guide: { ...guide, manifest_sha256: manifestHash }, ...overrides } } });
}

it('acknowledges a displayed initial guide lacking its hash before launching in another tab', async () => {
    mount({ mock_guide: guide });
    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledWith(expect.stringContaining('/mock-guide?'), expect.anything()));
    await vi.waitFor(() => expect(screen.getByRole('button', { name: 'Launch Mock Scanner →' })).toHaveProperty('disabled', false));
    await fireEvent.click(screen.getByRole('button', { name: 'Launch Mock Scanner →' }));
    await vi.waitFor(() => expect(popup.location.replace).toHaveBeenCalledWith(expect.stringContaining('run_public_id=mock-run-1')));
    const launch = fetchMock.mock.calls.find(([, init]) => init?.method === 'POST');
    expect(JSON.parse(String(launch?.[1]?.body))).toMatchObject({ test_kit_version_id: 2, expected_revision: 1, diagnostic_purpose: 'mock_rehearsal', instance_count: 1, pattern_mode: 'guided_mix', expected_mock_manifest_sha256: manifestHash });
    expect(screen.getByText('1. Mock Batch Setup')).toBeTruthy();
    expect(window.location.search).not.toContain('run_public_id');
    expect(popup.opener).toBeNull();
});

it('resumes the acknowledged run in the current scanner without creating another run', async () => {
    mount();
    await fireEvent.click(screen.getByRole('button', { name: 'Launch Mock Scanner →' }));
    await vi.waitFor(() => expect(popup.location.replace).toHaveBeenCalledOnce());
    await fireEvent.click(screen.getByRole('button', { name: 'Resume Mock Scanner →' }));
    await screen.findByRole('tab', { name: 'Camera' });
    expect(popup.location.replace).toHaveBeenCalledOnce();
    expect(window.location.search).toContain('run_public_id=mock-run-1');
    expect(fetchMock.mock.calls.filter(([, init]) => init?.method === 'POST')).toHaveLength(1);
});

it('hydrates a discovered resumable run in the current scanner without requiring a popup', async () => {
    mount({ resumable_mock_run: { public_id: 'retained-run', status: 'open', mock_config: { instance_count: 1, pattern_mode: 'guided_mix' } } });
    await fireEvent.click(screen.getByRole('button', { name: 'Resume Mock Scanner →' }));
    await screen.findByRole('tab', { name: 'Camera' });
    expect(popup.location.replace).not.toHaveBeenCalled();
    expect(window.location.search).toContain('run_public_id=retained-run');
    expect(fetchMock.mock.calls.filter(([, init]) => init?.method === 'POST')).toHaveLength(0);
});
it('cancels a retained Mock run from preparation without launching another run', async () => {
    mount({
        resumable_mock_run: {
            public_id: 'retained-run',
            status: 'open',
            mock_config: { instance_count: 1, pattern_mode: 'guided_mix' },
        },
    });
    vi.spyOn(window, 'prompt').mockReturnValue('No longer needed');
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    await fireEvent.click(screen.getByRole('button', { name: 'Cancel Mock session' }));
    await vi.waitFor(() => expect(fetchMock.mock.calls.some(([url, init]) => String(url).endsWith('/runs/retained-run/cancel') && init?.method === 'POST')).toBe(true));
    expect(fetchMock.mock.calls.filter(([url, init]) => init?.method === 'POST' && !String(url).endsWith('/runs/retained-run/cancel'))).toHaveLength(0);
    expect(screen.queryByRole('button', { name: 'Resume Mock Scanner →' })).toBeNull();
});

it('keeps preparation and its guide when staff declines session cancellation', async () => {
    mount({ resumable_mock_run: { public_id: 'retained-run', status: 'open', mock_config: { instance_count: 1, pattern_mode: 'guided_mix' } } });
    const prompt = vi.spyOn(window, 'prompt').mockReturnValue(null);

    await fireEvent.click(screen.getByRole('button', { name: 'Cancel Mock session' }));
    await vi.waitFor(() => expect(prompt).toHaveBeenCalled());
    expect(screen.getByRole('button', { name: 'Launch Mock Scanner →' })).toBeTruthy();
    expect(screen.queryByRole('tab', { name: 'Camera' })).toBeNull();
    expect(window.location.search).not.toContain('run_public_id');
    expect(fetchMock.mock.calls.filter(([, init]) => init?.method === 'POST')).toHaveLength(0);
});

it('launches a new rehearsal instead of silently resuming an existing same-configuration run', async () => {
    mount({
        resumable_mock_run: {
            public_id: 'expired-capture-set-run',
            status: 'open',
            mock_config: { instance_count: 1, pattern_mode: 'guided_mix' },
        },
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Launch Mock Scanner →' }));
    await vi.waitFor(() => expect(popup.location.replace).toHaveBeenCalledWith(
        expect.stringContaining('run_public_id=mock-run-1'),
    ));
    expect(popup.location.replace).not.toHaveBeenCalledWith(
        expect.stringContaining('run_public_id=expired-capture-set-run'),
    );
});

it('does not create a rehearsal if the browser blocks the new tab', async () => {
    openMock.mockReturnValue(null);
    mount();
    await fireEvent.click(screen.getByRole('button', { name: 'Launch Mock Scanner →' }));
    expect(fetchMock).not.toHaveBeenCalled();
    expect(screen.getByRole('alert').textContent).toContain('Allow pop-ups');
});

it('closes the reserved tab on a rejected launch and retains the setup error', async () => {
    fetchMock.mockResolvedValue({ ok: false, json: async () => ({ message: 'The Test Kit revision changed.' }) });
    mount();
    await fireEvent.click(screen.getByRole('button', { name: 'Launch Mock Scanner →' }));
    await vi.waitFor(() => expect(popup.close).toHaveBeenCalledOnce());
    expect(popup.location.replace).not.toHaveBeenCalled();
    expect(screen.getByRole('alert').textContent).toContain('The Test Kit revision changed.');
});

it('rejects a mismatched acknowledged guide without posting a launch', async () => {
    mount({ mock_guide: { ...guide, instance_count: 3, manifest_sha256: manifestHash } });
    fetchMock.mockResolvedValue({ ok: false, json: async () => ({ message: 'Guide unavailable.' }) });
    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled());
    await fireEvent.click(screen.getByRole('button', { name: 'Launch Mock Scanner →' }));
    expect(fetchMock.mock.calls.filter(([, init]) => init?.method === 'POST')).toHaveLength(0);
    expect(popup.location.replace).not.toHaveBeenCalled();
    expect(screen.getByRole('alert').textContent).toContain('exact shading guide');
});
