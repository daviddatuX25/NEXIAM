// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, waitFor, type RenderResult } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { OmrWorkbenchProjection } from '../../resources/js/lib/omrWorkbench';

const { router } = vi.hoisted(() => ({
    router: {
        on: vi.fn(() => () => {}),
        get: vi.fn(),
        post: vi.fn(),
        visit: vi.fn(),
        reload: vi.fn(),
    },
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...await importOriginal<Record<string, unknown>>(),
    router,
}));
vi.mock('@/lib/omrOperationalRun.js', () => ({
    createOmrOperationalPoller: () => ({ start: vi.fn(), stop: vi.fn() }),
}));
vi.mock('@/lib/toast.js', async (importOriginal) => ({
    ...await importOriginal<Record<string, unknown>>(),
    success: vi.fn(), error: vi.fn(), info: vi.fn(),
}));

import Capture from '../../resources/js/Pages/Assessment/Omr/Capture.svelte';

function projection(
    batchId: number,
    revision: number,
    sheets: OmrWorkbenchProjection['sheets'] = [],
    commands: OmrWorkbenchProjection['commands'] = ['capture_start', 'production_complete'],
): OmrWorkbenchProjection {
    return {
        workbench_id: `production:1:${batchId}`,
        execution_kind: 'production',
        revision,
        phase: 'capture',
        context: {
            execution_kind: 'production',
            session_id: 1,
            run_id: batchId,
            session: { id: 1 },
            run: { id: batchId },
            capture_target: {
                client_capture_id: 'ca4a8497-8504-47ad-8fce-a6e68f4d8b92',
                page_no: 1,
                target_revision: revision,
                instance_id: null,
                sheet_id: null,
                pending_capture_id: null,
                pending_capture_revision: null,
            },
        },
        sheets,
        evidence: { references: [], items: [], captures: [], redacted: true },
        recovery: { items: [], needs_attention: [], count: 0, outcome: null },
        completion: null,
        commands,
    };
}

function props(
    batchId = 1,
    revision = 1,
    sheets: OmrWorkbenchProjection['sheets'] = [],
    commands: OmrWorkbenchProjection['commands'] = ['capture_start', 'production_complete'],
) {
    return {
        camera_enabled: true,
        session: { id: 1, exam_session_id: 1, exam_code: 'OMR-TEST' },
        material: {
            qr_mode: 'qr_signed',
            registration_mode: 'fiducial_anchored',
            source_kind: 'system_designed',
        },
        template: { identity_region: null, items: [] },
        applicants: [],
        roster: [],
        batch: {
            id: batchId,
            expected_page_count: 1,
            template: { page_count: 1 },
            sheets,
        },
        run_status: null,
        workbench_projection: projection(batchId, revision, sheets, commands),
    };
}

let requests: string[];
let preflightResponse: Promise<Response> | null;
let resolvePreflight: ((response: Response) => void) | null;

function jsonResponse(value: unknown, status = 200): Response {
    return new Response(JSON.stringify(value), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

function mount(batchId = 1, revision = 1) {
    return render(Capture, props(batchId, revision));
}

async function selectImage(view: RenderResult<typeof Capture>): Promise<void> {
    const uploadButton = await screen.findByRole('button', { name: 'Upload image file' });
    await fireEvent.click(uploadButton);
    const inputs = view.container.querySelectorAll('input[type="file"]');
    const deckInput = inputs.item(inputs.length - 1) as HTMLInputElement;
    await fireEvent.change(deckInput, {
        target: {
            files: [new File(['sheet frame'], 'sheet.jpg', { type: 'image/jpeg' })],
        },
    });
}

beforeEach(() => {
    requests = [];
    preflightResponse = null;
    resolvePreflight = null;
    router.reload.mockReset();
    router.get.mockReset();
    router.post.mockReset();
    vi.stubGlobal('ResizeObserver', class {
        observe(): void {}
        unobserve(): void {}
        disconnect(): void {}
    });
    const mediaDevices = {
        getUserMedia: vi.fn().mockRejectedValue(Object.assign(new Error('denied'), { name: 'NotAllowedError' })),
        enumerateDevices: vi.fn().mockResolvedValue([]),
    };
    Object.defineProperty(navigator, 'mediaDevices', {
        configurable: true,
        value: mediaDevices,
    });
    vi.stubGlobal('fetch', vi.fn((input: RequestInfo | URL, init?: RequestInit) => {
        const url = String(input);
        requests.push(`${init?.method ?? 'GET'} ${url}`);
        if (url.endsWith('/preflight')) {
            if (preflightResponse) return preflightResponse;
            return Promise.resolve(jsonResponse({
                mode: 'markers',
                outcome: 'rejected',
                submit_allowed: false,
                detail: 'Registration was rejected.',
            }));
        }
        if (url.endsWith('/workbench') && (!init?.method || init.method === 'GET')) {
            const batchId = Number(url.match(/batches\/(\d+)\//)?.[1] ?? 1);
            return Promise.resolve(jsonResponse(projection(batchId, batchId)));
        }
        if (url.endsWith('/capture') && init?.method === 'POST') {
            return Promise.resolve(jsonResponse(projection(1, 2)));
        }
        throw new Error(`Unexpected OMR request: ${url}`);
    }));
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('production Capture adapter', () => {
    it('keeps a rejected real preflight from posting a capture', async () => {
        const view = mount();
        await selectImage(view);

        await waitFor(() => expect(requests.some((request) => request.includes('/preflight'))).toBe(true));
        expect(requests.some((request) => request.includes('/batches/1/workbench/capture'))).toBe(false);
        expect((await screen.findByRole('alert')).textContent).toContain('Registration was rejected.');
    });

    it('drops a successful preflight response after its frozen batch target changes', async () => {
        const gate = Promise.withResolvers<Response>();
        preflightResponse = gate.promise;
        resolvePreflight = gate.resolve;
        const view = mount();
        await selectImage(view);
        await waitFor(() => {
            expect(requests.some((request) => request.includes('/preflight'))).toBe(true);
            expect(resolvePreflight).not.toBeNull();
        });

        await view.rerender(props(2, 2));
        resolvePreflight?.(jsonResponse({
            mode: 'markers',
            outcome: 'validated',
            submit_allowed: true,
        }));
        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();

        expect(requests.some((request) => request.includes('/batches/1/workbench/capture') || request.includes('/batches/2/workbench/capture'))).toBe(false);
    });
    it('routes a selected production page retake through its ordinary replacement endpoint', async () => {
        vi.spyOn(window, 'prompt').mockReturnValue('The prior original is blurred.');
        const sheet = {
            id: 77,
            version: 1,
            client_capture_id: 'ca4a8497-8504-47ad-8fce-a6e68f4d8b92',
            status: 'pending_pages',
            expected_page_count: 2,
            capture_mode: 'auto',
            pages: [{ id: 101, page_no: 1, captured: true, capture_count: 1, current_revision: 1 }],
        };
        preflightResponse = Promise.resolve(jsonResponse({
            mode: 'markers',
            outcome: 'validated',
            submit_allowed: true,
        }));
        const view = render(Capture, {
            ...props(1, 1, [sheet]),
            batch: { ...props(1, 1, [sheet]).batch, expected_page_count: 2, template: { page_count: 2 }, sheets: [sheet] },
        });
        await fireEvent.click(await screen.findByRole('button', { name: 'Open scans gallery' }));
        await fireEvent.click(await screen.findByRole('button', { name: 'Retake P1' }));

        const inputs = view.container.querySelectorAll('input[type="file"]');
        await fireEvent.change(inputs.item(0) as HTMLInputElement, {
            target: {
                files: [new File(['replacement frame'], 'replacement.jpg', { type: 'image/jpeg' })],
            },
        });

        await waitFor(() => expect(router.post).toHaveBeenCalled());
        expect(router.post.mock.calls[0]?.[0]).toBe(
            '/scoring/sessions/1/omr/sheets/77/pages/1/replace',
        );
        expect(router.post.mock.calls[0]?.[1]).toMatchObject({
            expected_source_page_id: 101,
            expected_revision: 1,
            replacement_reason: 'The prior original is blurred.',
        });
    });

    it('does not offer capture without the production capture capability', async () => {
        const view = render(Capture, props(1, 1, [], ['production_complete']));
        await screen.findByRole('button', { name: 'Upload image file' });
        expect((screen.getByRole('button', { name: 'Upload image file' }) as HTMLButtonElement).disabled).toBe(true);
        const inputs = view.container.querySelectorAll('input[type="file"]');
        await fireEvent.change(inputs.item(inputs.length - 1) as HTMLInputElement, {
            target: {
                files: [new File(['sheet frame'], 'sheet.jpg', { type: 'image/jpeg' })],
            },
        });
        expect(requests.some((request) => request.includes('/preflight'))).toBe(false);
        expect(router.post).not.toHaveBeenCalled();
    });
});
