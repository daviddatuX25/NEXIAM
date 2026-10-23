// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { readOmrCaptureSessionRecovery } from '../../resources/js/lib/omrCaptureSessionRecovery';
import { captureAcceptanceFromProjection } from '../../resources/js/lib/omrCaptureWorkbenchAdapter';

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...await importOriginal<Record<string, unknown>>(),
    usePage: () => ({ props: { auth: { user: { id: 17, name: 'Test staff' } }, staff_navigation: [], errors: {} }, url: '/setup/exam-materials/omr-test' }),
    router: { on: () => () => {}, get: vi.fn(), post: vi.fn(), visit: vi.fn(), reload: vi.fn() },
}));

import OmrTest from '../../resources/js/Pages/Setup/OmrTest.svelte';

const publicId = 'retained-run';
const runUrl = `/setup/exam-materials/omr-test/runs/${publicId}`;
let current: OmrWorkbenchProjection;
let captures: FormData[];
let reads: number;
let retakeRequests: Record<string, unknown>[];
let captureResponseStatus: number | null;
let discardRequests: Record<string, unknown>[];
let cancelRequests: Record<string, unknown>[];

function emptyRun(): OmrWorkbenchProjection {
    return {
        workbench_id: `diagnostic:${publicId}`, execution_kind: 'diagnostic', revision: 1, phase: 'capture',
        context: {
            execution_kind: 'diagnostic', public_id: publicId, diagnostic_purpose: 'mock_rehearsal',
            capture_target: { instance_id: 'instance-1', sheet_id: null, client_capture_id: '5c887d50-72c5-4b29-ad18-2922e6d53d20', page_no: 1, target_revision: 1, pending_capture_id: null, pending_capture_revision: null },
            mock_progress: { page_count: 2 },
        },
        sheets: [], evidence: { references: [], items: [], captures: [], redacted: true },
        recovery: { items: [], needs_attention: [], count: 0, outcome: null }, completion: null,
        commands: ['capture_start', 'review_save'],
    };
}

beforeEach(() => {
    window.history.replaceState({}, '', `/setup/exam-materials/omr-test?run_public_id=${publicId}&test_kit_version_id=2`);
    vi.stubGlobal('ResizeObserver', class { observe() {} unobserve() {} disconnect() {} });
    vi.stubGlobal('URL', class extends URL {
        static createObjectURL = vi.fn(() => 'blob:crop-preview');
        static revokeObjectURL = vi.fn();
    });
    current = emptyRun(); captures = []; reads = 0; retakeRequests = []; discardRequests = []; cancelRequests = []; captureResponseStatus = null;
    vi.stubGlobal('fetch', vi.fn(async (url: string, init?: RequestInit) => {
        if (String(url).includes('/mock-guide?')) {
            return new Response(JSON.stringify({ mock_guide: { pages: [] } }));
        }
        if (String(url) === runUrl && (!init?.method || init.method === 'GET')) {
            reads += 1;
            return new Response(JSON.stringify(current));
        }
        if (String(url) === `${runUrl}/cancel` && init?.method === 'POST') {
            cancelRequests.push(JSON.parse(String(init.body)));
            current = {
                ...current,
                revision: current.revision + 1,
                status: 'cancelled',
                context: { ...current.context, status: 'cancelled', completed_at: '2026-10-23T00:00:00Z' },
                recovery: { ...current.recovery, outcome: { kind: 'success' } },
                commands: [],
            };
            return new Response(JSON.stringify(current));
        }
        if (String(url) === `${runUrl}/captures/11/discard` && init?.method === 'POST') {
            discardRequests.push(JSON.parse(String(init.body)));
            current = {
                ...current,
                revision: current.revision + 1,
                sheets: [],
                context: {
                    ...current.context,
                    capture_target: {
                        ...(current.context.capture_target as Record<string, unknown>),
                        client_capture_id: 'reopened-page-1',
                        page_no: 1,
                        target_revision: 2,
                        sheet_id: null,
                    },
                },
            };
            return new Response(JSON.stringify(current));
        }
        if (String(url) === `${runUrl}/capture` && init?.method === 'POST') {
            if (captureResponseStatus !== null) {
                return new Response(JSON.stringify({ message: 'The staff session expired.' }), { status: captureResponseStatus });
            }
            captures.push(init.body as FormData);
            const body = init.body as FormData;
            const previous = current.context.capture_target as Record<string, unknown>;
            const target = {
                workbenchId: current.workbench_id,
                clientCaptureId: previous.client_capture_id,
                pageNo: previous.page_no,
                captureRevision: previous.target_revision,
                instanceId: previous.instance_id,
                sheetId: previous.sheet_id,
                targetKey: JSON.stringify([current.workbench_id, previous.client_capture_id, previous.page_no, previous.target_revision, previous.instance_id, previous.sheet_id]),
            };
            current = {
                ...current, revision: current.revision + 1,
                context: {
                    ...current.context,
                    capture_target: { ...previous, page_no: 2, target_revision: 2, sheet_id: 11 },
                    capture_receipt: {
                        status: 'accepted', operation_id: body.get('operation_id'),
                        target_key: target.targetKey, page_no: previous.page_no,
                        capture_id: 11, page_id: 101, target,
                    },
                },
                sheets: [{ id: 11, status: 'queued', version: 1, pages: [{ page_no: 1, captured: true }] }],
            };
            return new Response(JSON.stringify(current));
        }
        if (String(url) === `${runUrl}/capture-actions` && init?.method === 'POST') {
            retakeRequests.push(JSON.parse(String(init.body)));
            const payload = retakeRequests[retakeRequests.length - 1];
            const grantTarget = payload.target as Record<string, unknown>;
            const sourceTarget = {
                workbenchId: current.workbench_id,
                clientCaptureId: 'source-client-1',
                pageNo: 1,
                captureRevision: 4,
                instanceId: 'instance-1',
                sheetId: 11,
            };
            const sourceTargetKey = JSON.stringify([
                sourceTarget.workbenchId, sourceTarget.clientCaptureId, sourceTarget.pageNo,
                sourceTarget.captureRevision, sourceTarget.instanceId, sourceTarget.sheetId,
            ]);
            current = {
                ...current,
                revision: current.revision + 1,
                context: {
                    ...current.context,
                    capture_target: {
                        instance_id: grantTarget.instance_id,
                        sheet_id: grantTarget.sheet_id,
                        client_capture_id: grantTarget.client_capture_id,
                        page_no: grantTarget.page_no,
                        target_revision: grantTarget.target_revision,
                        pending_capture_id: payload.pending_capture_id,
                        pending_capture_revision: payload.pending_capture_revision,
                        replaces_capture_id: payload.capture_id,
                    },
                    capture_receipt: {
                        status: 'accepted',
                        operation_id: 'source-operation-1',
                        target_key: sourceTargetKey,
                        page_no: 1,
                        capture_id: payload.capture_id,
                        target: { ...sourceTarget, targetKey: sourceTargetKey },
                    },
                },
            };
            return new Response(JSON.stringify(current));
        }
        throw new Error(`Unexpected scanner endpoint ${url}`);
    }));
});

afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

function mount(qrMode = 'qr_signed') {
    return render(OmrTest, { test_kit_context: { version_id: 2, revision: 1, code: 'SYN-MOCK', answer_sheet: { version_id: 28, registration_mode: 'document_bound', qr_mode: qrMode, page_count: 2 }, booklet: { version_id: 3, set_code: 'A' }, mock_guide: { manifest_sha256: 'a'.repeat(64), instance_count: 1, pattern_mode: 'guided_mix', pages: [{ page_no: 1, available: false, instances: [] }] } } });
}
it('resumes retained captures in the scanner despite a blocked previous command outcome', async () => {
    current.sheets = [{ id: 11, status: 'needs_review', version: 5, page_no: 1 }];
    current.recovery.outcome = {
        kind: 'blocked',
        code: 'diagnostic_no_next_page',
        blocked: true,
        message: 'The diagnostic run has no remaining capture target.',
    };
    mount();

    await screen.findByRole('tab', { name: 'Camera' });
    await fireEvent.click(screen.getByRole('tab', { name: /Scans Queue/ }));
    expect(await screen.findByRole('option', { name: /Review .*sheet 11/i })).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Launch Mock Scanner →' })).toBeNull();
    expect(captures).toHaveLength(0);
});
it('resumes a retained Mock run into the current scanner without opening a second tab', async () => {
    window.history.replaceState({}, '', '/setup/exam-materials/omr-test?test_kit_version_id=2');
    current.sheets = [{ id: 11, status: 'needs_review', version: 5, page_no: 1 }];
    const open = vi.spyOn(window, 'open');
    render(OmrTest, {
        test_kit_context: {
            version_id: 2,
            revision: 1,
            code: 'SYN-MOCK',
            answer_sheet: { version_id: 28, registration_mode: 'document_bound', qr_mode: 'qr_signed', page_count: 2 },
            booklet: { version_id: 3, set_code: 'A' },
            mock_guide: { manifest_sha256: 'a'.repeat(64), instance_count: 1, pattern_mode: 'guided_mix', pages: [] },
        },
        resumable_mock_run: {
            public_id: publicId,
            status: 'open',
            mock_config: { instance_count: 1, pattern_mode: 'guided_mix' },
        },
    });

    await fireEvent.click(screen.getByRole('button', { name: 'Resume Mock Scanner →' }));
    await screen.findByRole('tab', { name: 'Camera' });
    await fireEvent.click(screen.getByRole('tab', { name: /Scans Queue/ }));
    expect(await screen.findByRole('option', { name: /Review .*sheet 11/i })).toBeTruthy();
    expect(window.location.search).toContain(`run_public_id=${publicId}`);
    expect(open).not.toHaveBeenCalled();
    expect(vi.mocked(fetch).mock.calls.filter(([, init]) => init?.method === 'POST')).toHaveLength(0);
});

it('opens cancelled Mock history without starting another page-capture set', async () => {
    current.phase = 'complete';
    current.context.status = 'cancelled';
    current.context.capture_target = null;
    current.commands = [];
    current.sheets = [{ id: 11, status: 'needs_review', version: 5, page_no: 1 }];
    mount('qr_disabled');

    await screen.findByRole('tab', { name: 'Camera' });
    await fireEvent.click(screen.getByRole('tab', { name: /Scans Queue/ }));
    expect(await screen.findByRole('option', { name: /Review .*sheet 11/i })).toBeTruthy();
    expect(vi.mocked(fetch).mock.calls.filter(([, init]) => init?.method === 'POST')).toHaveLength(0);
});

it('returns to preparation with confirmed cancellation and access to retained history', async () => {
    window.sessionStorage.clear();
    current.commands = ['capture_start', 'review_save', 'diagnostic_cancel'];
    current.sheets = [{ id: 11, status: 'needs_review', version: 5, page_no: 1 }];
    vi.spyOn(window, 'prompt').mockReturnValue('Operator ended rehearsal');
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    mount();

    await fireEvent.click(await screen.findByRole('button', { name: 'Cancel Mock session' }));
    await waitFor(() => expect(cancelRequests).toHaveLength(1));
    expect(await screen.findByText('1. Mock Batch Setup')).toBeTruthy();
    expect(screen.getByText(/Mock session cancelled/)).toBeTruthy();
    expect(screen.getByRole('link', { name: 'View cancelled session' }).getAttribute('href')).toContain(`run_public_id=${publicId}`);
    expect(screen.queryByRole('tab', { name: 'Camera' })).toBeNull();
    expect(window.location.search).not.toContain('run_public_id');
});

it('refuses cancellation when the session changes during confirmation', async () => {
    window.sessionStorage.clear();
    current.commands = ['diagnostic_cancel'];
    vi.spyOn(window, 'prompt').mockReturnValue('End rehearsal');
    vi.spyOn(window, 'confirm').mockImplementation(() => {
        current = { ...current, revision: current.revision + 1 };
        return true;
    });
    mount();

    await fireEvent.click(await screen.findByRole('button', { name: 'Cancel Mock session' }));
    expect(await screen.findByText('The Mock session changed before confirmation. Review the current run and try again.')).toBeTruthy();
    expect(cancelRequests).toHaveLength(0);
    expect(screen.getByRole('tab', { name: 'Camera' })).toBeTruthy();
});

it('retakes the selected Page 1 capture through its exact server grant, not the latest receipt', async () => {
    const targetKey = 'diagnostic-selected-retake-target';
    const grant = {
        url: `${runUrl}/capture-actions`,
        authorization_id: 'retake-grant-page-1',
        capture_id: 11,
        expected_capture_version: 5,
        expected_revision: 1,
        page_no: 1,
        source_page_evidence_id: 101,
        pending_capture_id: null,
        pending_capture_revision: null,
        target: {
            workbench_id: `diagnostic:${publicId}`,
            client_capture_id: 'retake-page-1',
            page_no: 1,
            target_revision: 6,
            instance_id: 'instance-1',
            sheet_id: 11,
            target_key: targetKey,
        },
    };
    current.sheets = [
        { id: 11, status: 'failed', version: 5, page_no: 1, capture_retake: grant },
        { id: 22, status: 'failed', version: 3, page_no: 2 },
    ];
    const latestTarget = {
        workbenchId: `diagnostic:${publicId}`,
        clientCaptureId: 'latest-page-2-client',
        pageNo: 2,
        captureRevision: 2,
        instanceId: 'instance-1',
        sheetId: 22,
    };
    const latestTargetKey = JSON.stringify([
        latestTarget.workbenchId, latestTarget.clientCaptureId, latestTarget.pageNo,
        latestTarget.captureRevision, latestTarget.instanceId, latestTarget.sheetId,
    ]);
    current.context.capture_receipt = {
        status: 'accepted',
        operation_id: 'latest-page-2-operation',
        target_key: latestTargetKey,
        page_no: 2,
        capture_id: 22,
        target: { ...latestTarget, targetKey: latestTargetKey },
    };
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    mount();
    await fireEvent.click(await screen.findByRole('tab', { name: /Scans Queue/ }));
    await fireEvent.click(await screen.findByRole('button', { name: /Batch Queue/ }));
    const rowRetakeButtons = await screen.findAllByRole('button', { name: 'Retake page' });
    await fireEvent.click(rowRetakeButtons[0]!);

    await waitFor(() => expect(retakeRequests).toHaveLength(1));
    expect(retakeRequests[0]).toMatchObject({
        action: 'capture_retake',
        authorization_id: grant.authorization_id,
        idempotency_key: grant.authorization_id,
        expected_revision: 1,
        capture_id: 11,
        expected_capture_version: 5,
        page_no: 1,
        source_page_evidence_id: 101,
        pending_capture_id: null,
        pending_capture_revision: null,
        replacement_reason: 'Operator requested retake',
        target: grant.target,
    });
});
it('uses the typed discard projection revision for immediate recapture', async () => {
    current.sheets = [{ id: 11, status: 'failed', version: 5, page_no: 1 }];
    (current.context.capture_target as Record<string, unknown>).instance_id = null;
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const view = mount();
    await fireEvent.click(await screen.findByRole('tab', { name: /Scans Queue/ }));
    await fireEvent.click(await screen.findByRole('button', { name: /Batch Queue/ }));
    await fireEvent.click(await screen.findByRole('button', { name: 'Remove capture #11' }));

    await waitFor(() => expect(discardRequests).toHaveLength(1));
    expect(discardRequests[0]).toMatchObject({
        expected_revision: 1,
        expected_capture_version: 5,
        reason: 'Discarded by operator',
    });
    await fireEvent.click(await screen.findByRole('tab', { name: 'Camera' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Manual', exact: true }));
    await fireEvent.click(await screen.findByRole('button', { name: 'Upload image file', exact: true }));
    const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
    await fireEvent.change(input, { target: { files: [new File(['page'], 'page.png', { type: 'image/png' })] } });
    await fireEvent.click(await screen.findByRole('button', { name: 'Keep Original' }));

    await waitFor(() => expect(captures).toHaveLength(1));
    expect(captures[0]?.get('expected_revision')).toBe('2');
    expect(captures[0]?.get('instance_id')).toBeNull();
});
it('uses the diagnostic server target page count when mock preparation is absent', async () => {
    delete current.context.mock_progress;
    (current.context.capture_target as Record<string, unknown>).page_count = 2;
    render(OmrTest, { test_kit_context: { version_id: 2, revision: 1, code: 'GENERIC' } });
    await screen.findByRole('tab', { name: 'Camera' });
    expect(screen.getByText('Set page 1 of 2')).toBeTruthy();
});
it('retains the QR-disabled hold and identity recovery after a completed set has no capture target', async () => {
    current.context.capture_target = null;
    current.context.pending_capture = {
        id: 'completed-page-set',
        status: 'captured',
        pairing_code: 'pairing_missing',
        page_count: 2,
        active_page_no: 2,
    };
    delete current.context.mock_progress;
    render(OmrTest, { test_kit_context: { version_id: 2, revision: 1, code: 'GENERIC' } });

    await screen.findByRole('tab', { name: 'Camera' });
    expect(await screen.findByText(/All pages are captured, but applicant identity pairing is missing/)).toBeTruthy();
    expect(screen.getByText('Set page 2 of 2')).toBeTruthy();
    expect((screen.getByRole('button', { name: 'Upload image file' }) as HTMLButtonElement).disabled).toBe(true);
});
it('accepts an exact final-page receipt despite a blocked follow-up projection outcome', async () => {
    window.sessionStorage.clear();
    const view = mount();
    await screen.findByRole('tab', { name: 'Camera' });
    current.recovery.outcome = {
        kind: 'blocked',
        code: 'diagnostic_no_next_page',
        blocked: true,
        message: 'The diagnostic run has no remaining capture target.',
    };
    await fireEvent.click(screen.getByRole('button', { name: 'Manual', exact: true }));
    await fireEvent.click(await screen.findByRole('button', { name: 'Upload image file', exact: true }));
    const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
    await fireEvent.change(input, { target: { files: [new File(['last page'], 'page.png', { type: 'image/png' })] } });
    await fireEvent.click(await screen.findByRole('button', { name: 'Keep Original' }));

    await waitFor(() => expect(captures).toHaveLength(1));
    const retained = readOmrCaptureSessionRecovery(17, current.workbench_id);
    if (retained.kind === 'ready') {
        expect(captureAcceptanceFromProjection(current, retained.operation).status).toBe('accepted');
        expect(String(current.context.capture_receipt?.operation_id)).toBe(retained.operation.operationId);
    }
    const recovery = readOmrCaptureSessionRecovery(17, current.workbench_id);
    if (recovery.kind === 'ready') {
        expect(() => captureAcceptanceFromProjection(current, recovery.operation)).not.toThrow();
    }
    await waitFor(() => expect(readOmrCaptureSessionRecovery(17, current.workbench_id).kind).toBe('none'));
    expect(current.recovery.outcome?.kind).toBe('blocked');
});

it('submits the operator crop choice and retains the accepted capture in the camera queue', async () => {
    const view = mount();
    await screen.findByRole('tab', { name: 'Camera' });
    await fireEvent.click(screen.getByRole('button', { name: 'Manual', exact: true }));
    await fireEvent.click(await screen.findByRole('button', { name: 'Upload image file', exact: true }));
    const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
    await fireEvent.change(input, { target: { files: [new File(['physical image fixture'], 'page-1.png', { type: 'image/png' })] } });
    await fireEvent.click(await screen.findByRole('button', { name: 'Keep Original' }));
    await waitFor(() => expect(captures).toHaveLength(1));
    expect(Object.fromEntries([...captures[0].entries()].filter(([key]) => key !== 'image'))).toMatchObject({
        crop_policy: 'keep_original', page_no: '1', instance_id: 'instance-1', target_revision: '1', client_capture_id: '5c887d50-72c5-4b29-ad18-2922e6d53d20',
    });
    expect(screen.getByRole('tab', { name: 'Camera' }).getAttribute('aria-selected')).toBe('true');
    await fireEvent.click(await screen.findByRole('tab', { name: /Scans Queue/ }));
    expect(await screen.findByRole('option', { name: /sheet 11/i })).toBeTruthy();
    expect(vi.mocked(fetch).mock.calls.some(([url]) => String(url).includes('/scoring/'))).toBe(false);
});

it('shows ambiguous decisions from every sheet in Rapid Triage rather than only the selected sheet', async () => {
    current.sheets = [11, 12].map((id) => ({ id, status: 'needs_review', version: 1, pages: [{ page_no: 1, captured: true }] }));
    current.evidence.items = [11, 12].map((captureId, index) => ({
        id: 100 + index, item_id: `Q${index + 1}`, capture_id: captureId, page_no: 1, question_no: index + 1,
        state: 'ambiguous', options: [{ code: 'A', mean: 0.7 }, { code: 'B', mean: 0.6 }],
        machine: { state: 'ambiguous', selected_option: null, observed_options: ['A', 'B'], issue_codes: ['multiple_marks'] },
        evidence_hash: 'b'.repeat(64), parent_version: 1, item_revision: 1,
    }));
    mount();
    await fireEvent.click(await screen.findByRole('tab', { name: 'Rapid Triage' }));
    await waitFor(() => {
        expect(screen.getByRole('group', { name: 'Choose answer for question 1' })).toBeTruthy();
        expect(screen.getByRole('group', { name: 'Choose answer for question 2' })).toBeTruthy();
    });
});

it('refreshes queued diagnostic processing without creating another run or resubmitting a capture', async () => {
    current.phase = 'processing';
    current.sheets = [{ id: 11, status: 'queued', version: 1, pages: [{ page_no: 1, captured: true }] }];
    mount();
    await screen.findByRole('tab', { name: 'Camera' });
    const initialReads = reads;
    current = { ...current, revision: 2, phase: 'review', sheets: [{ ...current.sheets[0], status: 'needs_review', version: 2 }] };
    await waitFor(() => expect(reads).toBeGreaterThan(initialReads), { timeout: 2500 });
    expect(captures).toEqual([]);
    expect(vi.mocked(fetch).mock.calls.filter(([, init]) => init?.method === 'POST')).toEqual([]);
});

it('retains Manual review-before-submit behavior after visiting another workbench stage', async () => {
    const view = mount();
    await screen.findByRole('tab', { name: 'Camera' });
    await fireEvent.click(screen.getByRole('button', { name: 'Manual', exact: true }));
    await fireEvent.click(screen.getByRole('tab', { name: 'Review' }));
    await fireEvent.click(screen.getByRole('tab', { name: 'Camera' }));
    await fireEvent.click(await screen.findByRole('button', { name: 'Upload image file', exact: true }));
    const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
    await fireEvent.change(input, { target: { files: [new File(['image'], 'page.png', { type: 'image/png' })] } });
    await screen.findByRole('dialog', { name: 'Crop Check' });
    expect(captures).toEqual([]);
    await fireEvent.click(screen.getByRole('button', { name: 'Retake / Cancel' }));
    expect(captures).toEqual([]);
});

it('does not ingest a selected file behind the workbench batch queue', async () => {
    const view = mount();
    await screen.findByRole('tab', { name: 'Camera' });
    await fireEvent.click(await screen.findByRole('button', { name: 'Upload image file', exact: true }));
    await fireEvent.click(screen.getByRole('button', { name: /Batch Queue/ }));
    const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
    await fireEvent.change(input, { target: { files: [new File(['image'], 'page.png', { type: 'image/png' })] } });
    expect(captures).toEqual([]);
    expect(screen.queryByRole('dialog', { name: 'Crop Check' })).toBeNull();
});

it('uses the server-issued QR-disabled target even when the optional pending progress panel is absent', async () => {
    current.context.pending_capture = null;
    current.context.capture_target = {
        ...current.context.capture_target!,
        pending_capture_id: '12d9f5d1-39a5-4278-a2c1-308ed3887bbe',
        pending_capture_revision: 1,
    };
    const view = mount('qr_disabled');
    await screen.findByRole('tab', { name: 'Camera' });
    const upload = await screen.findByRole('button', { name: 'Upload image file', exact: true });
    expect((upload as HTMLButtonElement).disabled).toBe(false);
    await fireEvent.click(upload);
    const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
    await fireEvent.change(input, { target: { files: [new File(['page'], 'page.png', { type: 'image/png' })] } });
    await waitFor(() => expect(captures).toHaveLength(1));
    expect(captures[0].get('pending_capture_id')).toBe('12d9f5d1-39a5-4278-a2c1-308ed3887bbe');
    expect(captures[0].get('pending_capture_revision')).toBe('1');
});

it('uses the server-pinned document mode for Crop Check when preparation omits paper metadata', async () => {
    current.context.capture_target = {
        ...(current.context.capture_target as Record<string, unknown>),
        registration_mode: 'document_bound',
        qr_mode: 'qr_disabled',
        pending_capture_id: 'pending-run-page',
        pending_capture_revision: 1,
    };
    const view = render(OmrTest, {
        test_kit_context: { version_id: 2, revision: 1, code: 'SYN-MOCK', page_count: 2 },
    });
    await screen.findByRole('tab', { name: 'Camera' });
    await fireEvent.click(screen.getByRole('button', { name: 'Manual', exact: true }));
    const input = view.container.querySelector('[data-omr-camera-deck] input[type="file"]') as HTMLInputElement;
    expect(input.multiple).toBe(false);
    await fireEvent.change(input, { target: { files: [new File(['fixture'], 'page.png', { type: 'image/png' })] } });
    await screen.findByRole('button', { name: 'Straighten & Use' });
    await fireEvent.click(screen.getByRole('button', { name: 'Retake / Cancel' }));
    await waitFor(() => expect(input.disabled).toBe(false));
    expect(captures).toHaveLength(0);
});
it('retains the exact operation recovery reference when both submission and reconciliation transport fail', async () => {
    window.sessionStorage.clear();
    const view = mount();
    await screen.findByRole('tab', { name: 'Camera' });
    const fetcher = vi.mocked(fetch);
    const originalFetch = fetcher.getMockImplementation()!;
    fetcher.mockImplementation(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = new URL(String(input), window.location.origin);
        if (url.pathname === `${runUrl}/capture` && init?.method === 'POST') {
            throw new TypeError('network response lost');
        }
        if (url.pathname === runUrl && url.searchParams.has('capture_operation_id')) {
            return new Response(JSON.stringify({ message: 'The staff session expired.' }), { status: 419 });
        }
        return originalFetch(input, init);
    });

    await fireEvent.click(screen.getByRole('button', { name: 'Manual', exact: true }));
    await fireEvent.click(await screen.findByRole('button', { name: 'Upload image file', exact: true }));
    const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
    await fireEvent.change(input, { target: { files: [new File(['page'], 'page.png', { type: 'image/png' })] } });
    await fireEvent.click(await screen.findByRole('button', { name: 'Keep Original' }));

    await waitFor(() => expect(readOmrCaptureSessionRecovery(17, `diagnostic:${publicId}`).kind).toBe('ready'));
    expect(captures).toHaveLength(0);
    const saved = readOmrCaptureSessionRecovery(17, `diagnostic:${publicId}`);
    if (saved.kind !== 'ready') throw new Error('Expected the exact operation reference to survive the failed request.');
    view.unmount();

    mount();
    const checkOutcome = await screen.findByRole('button', { name: 'Check outcome' });
    const priorReconciliations = fetcher.mock.calls.filter(([url]) =>
        new URL(String(url), window.location.origin).searchParams.has('capture_operation_id')
    ).length;
    await fireEvent.click(checkOutcome);
    await waitFor(() => expect(fetcher.mock.calls.filter(([url]) =>
        new URL(String(url), window.location.origin).searchParams.has('capture_operation_id')
    ).length).toBeGreaterThan(priorReconciliations));
    const reconciliationRequest = fetcher.mock.calls
        .filter(([url]) => new URL(String(url), window.location.origin).searchParams.has('capture_operation_id'))
        .at(-1);
    const reconciliationUrl = new URL(String(reconciliationRequest?.[0]), window.location.origin);
    expect(reconciliationUrl.searchParams.get('capture_operation_id')).toBe(saved.operation.operationId);
    expect(reconciliationUrl.searchParams.get('capture_idempotency_key')).toBe(saved.operation.idempotencyKey);
    expect(readOmrCaptureSessionRecovery(17, `diagnostic:${publicId}`).kind).toBe('ready');
});
it('clears only an exact submit 419 reference while reconciliation 419 stays held', async () => {
    window.sessionStorage.clear();
    captureResponseStatus = 419;
    const view = mount();
    await screen.findByRole('tab', { name: 'Camera' });
    await fireEvent.click(screen.getByRole('button', { name: 'Manual', exact: true }));
    await fireEvent.click(await screen.findByRole('button', { name: 'Upload image file', exact: true }));
    const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
    await fireEvent.change(input, { target: { files: [new File(['page'], 'page.png', { type: 'image/png' })] } });
    await fireEvent.click(await screen.findByRole('button', { name: 'Keep Original' }));
    await waitFor(() => expect(readOmrCaptureSessionRecovery(17, `diagnostic:${publicId}`).kind).toBe('none'));
    expect(vi.mocked(fetch).mock.calls.some(([url]) =>
        new URL(String(url), window.location.origin).searchParams.has('capture_operation_id')
    )).toBe(false);

    view.unmount();
    mount();
    await screen.findByRole('tab', { name: 'Camera' });
    expect(readOmrCaptureSessionRecovery(17, `diagnostic:${publicId}`).kind).toBe('none');
});

it('retires transport and upload warnings when the exact lost-response operation is accepted', async () => {
    window.sessionStorage.clear();
    const view = mount();
    await screen.findByRole('tab', { name: 'Camera' });
    const fetcher = vi.mocked(fetch);
    const originalFetch = fetcher.getMockImplementation()!;
    fetcher.mockImplementation(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = new URL(String(input), window.location.origin);
        if (url.pathname === `${runUrl}/capture` && init?.method === 'POST') {
            await originalFetch(input, init);
            throw new TypeError('Response lost after server acceptance');
        }
        if (url.pathname === runUrl && url.searchParams.has('capture_operation_id')) {
            return new Response(JSON.stringify(current));
        }
        return originalFetch(input, init);
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Manual', exact: true }));
    const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
    await fireEvent.change(input, { target: { files: [new File(['page'], 'page.png', { type: 'image/png' })] } });
    await fireEvent.click(await screen.findByRole('button', { name: 'Keep Original' }));
    await waitFor(() => expect(captures).toHaveLength(1));
    await waitFor(() => expect(readOmrCaptureSessionRecovery(17, `diagnostic:${publicId}`).kind).toBe('none'));
    await waitFor(() => {
        expect(view.container.querySelector('[data-omr-diagnostic-error]')).toBeNull();
        expect(screen.queryByText(/The previous upload outcome is not confirmed/)).toBeNull();
    });
});
