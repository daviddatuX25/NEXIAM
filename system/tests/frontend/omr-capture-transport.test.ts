// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vitest';
import { isOmrWorkbenchProjection } from '../../resources/js/lib/omrWorkbench';
import { createOmrWorkbenchClient, isOmrWorkbenchValidationError } from '../../resources/js/lib/omrWorkbenchClient';
import { acceptedCaptureReferenceFromProjection, captureAcceptanceFromProjection } from '../../resources/js/lib/omrCaptureWorkbenchAdapter';
import { freezeOmrCaptureTargetContext } from '../../resources/js/lib/omrCameraAcquisition';
import type { OmrCaptureOperation } from '../../resources/js/lib/omrCaptureProtocol';

const target = freezeOmrCaptureTargetContext({ workbenchId: 'production:7:9', clientCaptureId: 'group-1', pageNo: 1, captureRevision: 4, instanceId: null, sheetId: null })!;
const operation: OmrCaptureOperation = { operationId: 'operation-1', idempotencyKey: 'intent-1', target, sourceId: 'camera-1', sourceGeneration: 2, configRevision: 3, trigger: 'shutter', mode: 'manual' };
function projection(receipt?: Record<string, unknown>) {
    return { workbench_id: 'production:7:9', execution_kind: 'production', revision: 5, phase: 'capture', context: receipt ? { capture_receipt: receipt } : {}, sheets: [], evidence: { references: [], items: [], captures: [], redacted: true }, recovery: { items: [], needs_attention: [], count: 0, outcome: null }, completion: null, commands: ['capture_start'] };
}
function clientFixture() {
    const fetchImpl = vi.fn(async () => new Response(JSON.stringify(projection()), { status: 200, headers: { 'Content-Type': 'application/json' } }));
    return { fetchImpl, client: createOmrWorkbenchClient({ workbenchId: 'production:7:9', executionKind: 'production', urls: { show: '/workbench', capture: '/capture' }, fetchImpl }) };
}
it('accepts bounded final-page evidence and its exact receipt, while rejecting excessive nesting', () => {
    const value = projection();
    const item = {
        id: 1,
        capture_id: 9,
        page_no: 1,
        options: [{ region: { x: 0.25, y: 0.3, w: 0.1, h: 0.01 } }],
    };
    const targetRecord = {
        workbenchId: target.workbenchId,
        clientCaptureId: target.clientCaptureId,
        pageNo: target.pageNo,
        captureRevision: target.captureRevision,
        instanceId: target.instanceId,
        sheetId: target.sheetId,
        targetKey: target.targetKey,
    };
    value.context = {
        capture_receipt: {
            operation_id: operation.operationId,
            target_key: target.targetKey,
            page_no: target.pageNo,
            status: 'accepted',
            capture_id: 9,
            target: targetRecord,
        },
    };
    value.sheets = [{ id: 9, status: 'needs_review', version: 1 }];
    value.evidence = {
        references: [],
        items: [item],
        captures: [{ capture_id: 9, evidence: { items: [item] } }],
        comparison: { capture_id: 9, status: 'needs_review' },
        redacted: true,
    };

    expect(isOmrWorkbenchProjection(value)).toBe(true);
    expect(captureAcceptanceFromProjection(value, operation)).toMatchObject({
        operationId: operation.operationId,
        targetKey: target.targetKey,
        status: 'accepted',
        captureId: 9,
    });
    expect(acceptedCaptureReferenceFromProjection(value)?.receipt.captureId).toBe(9);

    const excessive = structuredClone(value);
    let nested: unknown = 'bounded';
    for (let index = 0; index < 11; index += 1) {
        nested = { child: nested };
    }
    (excessive.context as Record<string, unknown>).unsupported_depth = nested;
    expect(isOmrWorkbenchProjection(excessive)).toBe(false);
});

describe('OMR original-image capture transport', () => {
    it('submits a staged original without manufacturing or uploading another image', async () => {
        const { client, fetchImpl } = clientFixture();
        await client.captureStart({ kind: 'staged', stagedFrameId: 'frame-1', pairingId: 'pair-1', commandId: 'command-1' }, { operation_id: 'operation-1', idempotency_key: 'intent-1' }, 4, { token: 'private-controller-token', generation: 2 });
        const init = fetchImpl.mock.calls[0]?.[1] as RequestInit;
        const body = init.body as FormData;
        expect(body.get('image')).toBeNull();
        expect(body.get('staged_frame_id')).toBe('frame-1');
        expect(body.get('pairing_id')).toBe('pair-1');
        expect(body.get('camera_command_id')).toBe('command-1');
        expect([...body.values()]).not.toContain('private-controller-token');
        const headers = new Headers(init.headers);
        expect(headers.get('X-Omr-Camera-Token')).toBe('private-controller-token');
        expect(headers.get('X-Omr-Camera-Role')).toBe('controller');
        expect(headers.get('X-Omr-Camera-Generation')).toBe('2');
    });

    it('rejects both original-image authorities before sending a request', async () => {
        const { client, fetchImpl } = clientFixture();
        await expect(client.captureStart({ kind: 'raw', file: new Blob(['original']) }, { staged_frame_id: 'other-frame' }, 4)).rejects.toMatchObject({ code: 'capture_source_duplicate' });
        expect(fetchImpl).not.toHaveBeenCalled();
    });

    it('requires controller credentials for a staged image rather than relying on the staff session', async () => {
        const { client, fetchImpl } = clientFixture();
        await expect(client.captureStart({ kind: 'staged', stagedFrameId: 'frame-1', pairingId: 'pair-1', commandId: 'command-1' }, {}, 4)).rejects.toMatchObject({ code: 'camera_authorization_required' });
        expect(fetchImpl).not.toHaveBeenCalled();
    });

    it('retains the original image for local capture', async () => {
        const { client, fetchImpl } = clientFixture();
        await client.captureStart({ kind: 'raw', file: new Blob(['original'], { type: 'image/jpeg' }) }, {}, 4);
        const body = (fetchImpl.mock.calls[0]?.[1] as RequestInit).body as FormData;
        expect(await (body.get('image') as Blob).text()).toBe('original');
        expect(body.get('staged_frame_id')).toBeNull();
    });
});
it.each([401, 419])('surfaces definite auth responses as typed transport failures (%i)', async (status) => {
    const fetchImpl = vi.fn(async () => new Response(JSON.stringify({ message: 'Authentication required.' }), { status, headers: { 'Content-Type': 'application/json' } }));
    const client = createOmrWorkbenchClient({ workbenchId: 'production:7:9', executionKind: 'production', urls: { show: '/workbench', capture: '/capture' }, fetchImpl });
    await expect(client.captureStart({ kind: 'raw', file: new Blob(['original']) }, { operation_id: operation.operationId, idempotency_key: operation.idempotencyKey }, 4)).rejects.toMatchObject({ status });
});

it('reconciles with the exact original operation identity through the typed read endpoint', async () => {
    const fetchImpl = vi.fn(async () => new Response(JSON.stringify({ message: 'Session expired.' }), { status: 419, headers: { 'Content-Type': 'application/json' } }));
    const client = createOmrWorkbenchClient({ workbenchId: 'production:7:9', executionKind: 'production', urls: { show: '/workbench', capture: '/capture' }, fetchImpl });
    await expect(client.reconcileCapture(operation.operationId, operation.idempotencyKey)).rejects.toMatchObject({ status: 419 });
    const [input, init] = fetchImpl.mock.calls[0]!;
    const url = new URL(String(input), window.location.origin);
    expect(url.searchParams.get('capture_operation_id')).toBe(operation.operationId);
    expect(url.searchParams.get('capture_idempotency_key')).toBe(operation.idempotencyKey);
    expect(init?.method).toBe('GET');
});
it('classifies only explicit Laravel validation errors as definite request rejection', async () => {
    const validationClient = createOmrWorkbenchClient({
        workbenchId: 'production:7:9',
        executionKind: 'production',
        urls: { show: '/workbench', capture: '/capture' },
        fetchImpl: vi.fn(async () => new Response(JSON.stringify({
            message: 'The given data was invalid.',
            errors: { instance_id: ['The instance id field must be a string.'] },
        }), { status: 422, headers: { 'Content-Type': 'application/json' } })),
    });
    let validationError: unknown;
    try {
        await validationClient.captureStart(
            { kind: 'raw', file: new Blob(['original']) },
            { operation_id: operation.operationId, idempotency_key: operation.idempotencyKey },
            4,
        );
    } catch (error) {
        validationError = error;
    }
    expect(isOmrWorkbenchValidationError(validationError)).toBe(true);

    const untyped422Client = createOmrWorkbenchClient({
        workbenchId: 'production:7:9',
        executionKind: 'production',
        urls: { show: '/workbench', capture: '/capture' },
        fetchImpl: vi.fn(async () => new Response(JSON.stringify({ message: 'Request failed.' }), { status: 422 })),
    });
    await expect(untyped422Client.captureStart(
        { kind: 'raw', file: new Blob(['original']) },
        { operation_id: operation.operationId, idempotency_key: operation.idempotencyKey },
        4,
    )).rejects.toMatchObject({ status: 422, code: 'workbench_request_failed' });
});


describe('OMR domain acknowledgment identity', () => {
    const accepted = { operation_id: operation.operationId, target_key: target.targetKey, status: 'accepted', capture_id: 41, page_id: 57, page_no: 1 };
    it('does not treat an HTTP projection or a different operation receipt as capture acceptance', () => {
        expect(() => captureAcceptanceFromProjection(projection(), operation)).toThrow();
        expect(() => captureAcceptanceFromProjection(projection({ ...accepted, operation_id: 'prior-operation' }), operation)).toThrow();
        expect(() => captureAcceptanceFromProjection(projection({ ...accepted, target_key: 'other-target' }), operation)).toThrow();
        expect(() => captureAcceptanceFromProjection(projection({ ...accepted, page_no: 2 }), operation)).toThrow();
    });
    it('requires persisted evidence identity before announcing an accepted result', () => {
        expect(() => captureAcceptanceFromProjection(projection({ ...accepted, capture_id: null, page_id: null }), operation)).toThrow();
        expect(captureAcceptanceFromProjection(projection(accepted), operation)).toMatchObject({ operationId: operation.operationId, targetKey: target.targetKey, status: 'accepted', captureId: 41, pageNo: 1 });
    });
});

it('correlates diagnostic recognition feedback independently from the accepted pending-set identity', () => {
    const diagnosticTarget = freezeOmrCaptureTargetContext({
        workbenchId: 'diagnostic:recognition-feedback', clientCaptureId: 'page-1',
        pageNo: 1, captureRevision: 4, instanceId: null, sheetId: null,
    })!;
    const reference = { operationId: 'diagnostic-operation', target: diagnosticTarget };
    const value = {
        ...projection({
            operation_id: reference.operationId, target_key: diagnosticTarget.targetKey,
            status: 'accepted', capture_id: 41, diagnostic_capture_id: 7, page_no: 1,
        }),
        workbench_id: diagnosticTarget.workbenchId,
        execution_kind: 'diagnostic',
        sheets: [
            { id: 41, status: 'failed', version: 1, failure: { message: 'Unrelated earlier capture', stage: 'processing', recovery: 'Do not attach this recovery' } },
            { id: 7, status: 'failed', version: 1, failure: { message: 'The page boundary could not be found.', stage: 'registration', recovery: 'Show all four sheet corners.' } },
        ],
    };
    expect(captureAcceptanceFromProjection(value, reference)).toMatchObject({
        status: 'accepted', captureId: 41,
        message: 'The page boundary could not be found.',
        stage: 'registration', recovery: 'Show all four sheet corners.',
    });
});
    it('keeps an exact operation held when the server cannot yet resolve its receipt', () => {
        const unresolved = captureAcceptanceFromProjection(projection({
            operation_id: operation.operationId,
            target_key: '',
            status: 'pending',
            capture_id: null,
            page_no: null,
            client_capture_id: null,
        }), operation);

        expect(unresolved).toMatchObject({
            operationId: operation.operationId,
            targetKey: target.targetKey,
            status: 'pending',
            pageNo: target.pageNo,
        });
        expect(unresolved.stage).toBe('recovery');
        expect(unresolved.recovery).toContain('Keep this exact operation held');
        expect(unresolved.message).toContain('request outcome remains unconfirmed');
        expect(unresolved.message).not.toContain('processing');
        expect(unresolved.captureId).toBeUndefined();
    });
