import {
    appendOmrClientQuadHint,
    type OmrClientQuadHint,
} from './omrClientQuadHint';
import { csrfHeaders, newIdempotencyKey } from './utils.js';
import {
    acceptOmrWorkbenchProjection,
    isOmrWorkbenchProjection,
    type OmrExecutionKind,
    type OmrWorkbenchCommand,
    type OmrWorkbenchProjection,
} from './omrWorkbench.js';

/** JSON fields supplied to a named workbench command. */
export type OmrWorkbenchCommandPayload = Readonly<Record<string, unknown>>;

export type OmrWorkbenchResourceId = string | number;

export type OmrWorkbenchCaptureInput =
    | Readonly<{ kind: 'raw'; file: Blob }>
    | Readonly<{
          kind: 'staged';
          stagedFrameId: string;
          pairingId: string;
          commandId: string;
      }>;

export type OmrCameraControllerAuthorization = Readonly<{
    token: string;
    generation: number;
}>;

/**
 * A URL is supplied by the route host. Builders are supported for the two
 * resource-scoped endpoints, so this client never reconstructs route paths.
 */
export type OmrWorkbenchUrl =
    string | ((resourceId: OmrWorkbenchResourceId) => string);

/** Exact route URLs exposed by one workbench host. */
export interface OmrWorkbenchClientUrls {
    show: string;
    capture?: string;
    retry?: OmrWorkbenchUrl;
    review?: OmrWorkbenchUrl;
    resolveApplicant?: OmrWorkbenchUrl;
    resolveDuplicate?: OmrWorkbenchUrl;
    compare?: string;
    complete?: string;
    cancel?: string;
}

export interface OmrWorkbenchClientConfig {
    readonly workbenchId: string;
    readonly executionKind: OmrExecutionKind;
    readonly urls: OmrWorkbenchClientUrls;
    readonly fetchImpl?: OmrWorkbenchFetch;
    readonly csrfToken?: string;
    readonly headers?: HeadersInit;
    readonly credentials?: RequestCredentials;
}

export type OmrWorkbenchFetch = (
    input: RequestInfo | URL,
    init?: RequestInit,
) => Promise<Response>;

export type OmrWorkbenchErrorKind =
    'stale' | 'denied' | 'invalid' | 'expired' | 'conflict' | 'infrastructure';

export interface OmrWorkbenchErrorInit {
    readonly code?: string;
    readonly status?: number;
    readonly details?: unknown;
    readonly cause?: unknown;
}

export type OmrWorkbenchErrorFeedback = Readonly<{
    message: string;
    stage: string | null;
    recovery: string | null;
    supportCode: string | null;
    kind: OmrWorkbenchErrorKind;
    code: string;
    status: number;
}>;

export function formatOmrWorkbenchError(
    error: unknown,
    fallback = 'The OMR workbench request could not be completed.',
): OmrWorkbenchErrorFeedback {
    const typed = error instanceof OmrWorkbenchClientError ? error : null;
    const record = error !== null && typeof error === 'object'
        ? error as Record<string, unknown>
        : {};
    const details = typed?.details ?? record.details;
    const safeDetails = isRecord(details) ? details : {};
    const message = typed?.message ?? (typeof record.message === 'string' ? record.message : fallback);
    return {
        message: message.trim() === '' ? fallback : message,
        stage: safeString(safeDetails.stage) ?? safeString(record.stage),
        recovery: safeString(safeDetails.recovery) ?? safeString(record.recovery),
        supportCode: safeString(safeDetails.support_code) ?? safeString(record.supportCode),
        kind: typed?.kind ?? 'infrastructure',
        code: typed?.code ?? safeString(record.code) ?? 'workbench_request_failed',
        status: typed?.status ?? (typeof record.status === 'number' ? record.status : 0),
    };
}

function safeString(value: unknown): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

/** Base error raised by the typed workbench transport. */
export class OmrWorkbenchClientError extends Error {
    readonly kind: OmrWorkbenchErrorKind;
    readonly code: string;
    readonly status: number;
    readonly details: unknown;

    constructor(
        kind: OmrWorkbenchErrorKind,
        message: string,
        init: OmrWorkbenchErrorInit = {},
    ) {
        super(
            message,
            init.cause === undefined ? undefined : { cause: init.cause },
        );
        this.name = 'OmrWorkbenchClientError';
        this.kind = kind;
        this.code = init.code ?? kind;
        this.status = init.status ?? 0;
        this.details = init.details ?? null;
    }
}

export class OmrWorkbenchStaleError extends OmrWorkbenchClientError {
    constructor(
        message = 'The OMR workbench is stale.',
        init: OmrWorkbenchErrorInit = {},
    ) {
        super('stale', message, init);
        this.name = 'OmrWorkbenchStaleError';
    }
}

export class OmrWorkbenchDeniedError extends OmrWorkbenchClientError {
    constructor(
        message = 'The OMR workbench command was denied.',
        init: OmrWorkbenchErrorInit = {},
    ) {
        super('denied', message, init);
        this.name = 'OmrWorkbenchDeniedError';
    }
}

export class OmrWorkbenchInvalidError extends OmrWorkbenchClientError {
    constructor(
        message = 'The OMR workbench request was invalid.',
        init: OmrWorkbenchErrorInit = {},
    ) {
        super('invalid', message, init);
        this.name = 'OmrWorkbenchInvalidError';
    }
}

export class OmrWorkbenchExpiredError extends OmrWorkbenchClientError {
    constructor(
        message = 'The OMR workbench continuation has expired.',
        init: OmrWorkbenchErrorInit = {},
    ) {
        super('expired', message, init);
        this.name = 'OmrWorkbenchExpiredError';
    }
}

export class OmrWorkbenchConflictError extends OmrWorkbenchClientError {
    constructor(
        message = 'The OMR workbench has a concurrent conflict.',
        init: OmrWorkbenchErrorInit = {},
    ) {
        super('conflict', message, init);
        this.name = 'OmrWorkbenchConflictError';
    }
}
export class OmrWorkbenchInfrastructureError extends OmrWorkbenchClientError {
    constructor(
        message = 'The OMR workbench service is unavailable.',
        init: OmrWorkbenchErrorInit = {},
    ) {
        super('infrastructure', message, init);
        this.name = 'OmrWorkbenchInfrastructureError';
    }
}

export function isOmrWorkbenchClientError(
    value: unknown,
): value is OmrWorkbenchClientError {
    return value instanceof OmrWorkbenchClientError;
}
export function isOmrWorkbenchValidationError(
    value: unknown,
): value is OmrWorkbenchInvalidError {
    return value instanceof OmrWorkbenchInvalidError &&
        value.status === 422 &&
        value.code === 'form_request_validation_failed';
}

export interface OmrWorkbenchClient {
    readonly workbenchId: string;
    readonly executionKind: OmrExecutionKind;
    readonly load: () => Promise<OmrWorkbenchProjection>;
    readonly loadCaptureTarget: (sheetId: OmrWorkbenchResourceId) => Promise<OmrWorkbenchProjection>;
    readonly reconcileCapture: (
        operationId: string,
        idempotencyKey: string,
    ) => Promise<OmrWorkbenchProjection>;
    readonly captureTransition: (
        projectedUrl: string,
        payload: OmrWorkbenchCommandPayload,
        revision: number,
    ) => Promise<OmrWorkbenchProjection>;
    readonly captureStart: (
        input: OmrWorkbenchCaptureInput,
        payload: OmrWorkbenchCommandPayload,
        revision: number,
        authorization?: OmrCameraControllerAuthorization,
    ) => Promise<OmrWorkbenchProjection>;
    readonly captureRetry: (
        captureId: OmrWorkbenchResourceId,
        payload: OmrWorkbenchCommandPayload,
        revision: number,
    ) => Promise<OmrWorkbenchProjection>;
    readonly reviewSave: (
        itemId: OmrWorkbenchResourceId,
        payload: OmrWorkbenchCommandPayload,
        revision: number,
    ) => Promise<OmrWorkbenchProjection>;
    readonly resolveApplicant: (
        sheetId: OmrWorkbenchResourceId,
        payload: OmrWorkbenchCommandPayload,
        revision: number,
    ) => Promise<OmrWorkbenchProjection>;
    readonly resolveDuplicate: (
        survivorSheetId: OmrWorkbenchResourceId,
        payload: OmrWorkbenchCommandPayload,
        revision: number,
    ) => Promise<OmrWorkbenchProjection>;
    readonly diagnosticCompare: (
        revision: number,
    ) => Promise<OmrWorkbenchProjection>;
    readonly diagnosticComplete: (
        revision: number,
    ) => Promise<OmrWorkbenchProjection>;
    readonly diagnosticCancel: (
        payload: OmrWorkbenchCommandPayload,
        revision: number,
    ) => Promise<OmrWorkbenchProjection>;
    readonly productionComplete: (
        revision: number,
    ) => Promise<OmrWorkbenchProjection>;
}

const COMMAND_CAPTURE_START = 'capture_start' as OmrWorkbenchCommand;
const COMMAND_CAPTURE_RETRY = 'capture_retry' as OmrWorkbenchCommand;
const COMMAND_REVIEW_SAVE = 'review_save' as OmrWorkbenchCommand;
const COMMAND_RESOLVE_APPLICANT = 'resolve_applicant' as OmrWorkbenchCommand;
const COMMAND_RESOLVE_DUPLICATE = 'resolve_duplicate' as OmrWorkbenchCommand;
const COMMAND_DIAGNOSTIC_COMPARE = 'diagnostic_compare' as OmrWorkbenchCommand;
const COMMAND_DIAGNOSTIC_COMPLETE =
    'diagnostic_complete' as OmrWorkbenchCommand;
const COMMAND_DIAGNOSTIC_CANCEL =
    'diagnostic_cancel' as OmrWorkbenchCommand;
const COMMAND_PRODUCTION_COMPLETE =
    'production_complete' as OmrWorkbenchCommand;

const RESERVED_CONTEXT_FIELDS = new Set([
    'workbench_id',
    'execution_kind',
    'kind',
    'mode',
    'run_id',
    'batch_id',
    'actor_id',
    'session_id',
    'grading_session_id',
    'route_session_id',
    'route_context',
    'context',
]);

export function createOmrWorkbenchClient(
    config: OmrWorkbenchClientConfig,
): OmrWorkbenchClient {
    const workbenchId = assertWorkbenchId(config.workbenchId);
    const executionKind = assertExecutionKind(config.executionKind);
    const urls = freezeUrls(config.urls);
    const fetchImpl = config.fetchImpl ?? ((input, init) => fetch(input, init));
    const credentials = config.credentials ?? 'same-origin';
    let latestProjection: OmrWorkbenchProjection | null = null;

    const rememberProjection = (value: unknown): OmrWorkbenchProjection => {
        const projection = unwrapProjection(value);

        if (!isOmrWorkbenchProjection(projection)) {
            throw new OmrWorkbenchInvalidError(
                'The OMR workbench returned a malformed projection.',
                { code: 'projection_invalid', details: value },
            );
        }

        if (
            projection.workbench_id !== workbenchId ||
            projection.execution_kind !== executionKind
        ) {
            throw new OmrWorkbenchInvalidError(
                'The OMR workbench projection does not match this route context.',
                {
                    code: 'projection_identity_mismatch',
                    details: projection,
                },
            );
        }

        if (
            latestProjection !== null &&
            projection.revision < latestProjection.revision
        ) {
            throw new OmrWorkbenchStaleError(
                'The OMR workbench returned an older revision.',
                {
                    code: 'projection_revision_stale',
                    details: projection,
                },
            );
        }

        latestProjection = projection;
        return projection;
    };

    const assertCommand = (command: OmrWorkbenchCommand): void => {
        if (latestProjection === null) {
            return;
        }

        if (!latestProjection.commands.includes(command)) {
            throw new OmrWorkbenchDeniedError(
                'The requested OMR command is not available in the current projection.',
                { code: 'command_not_projected', details: { command } },
            );
        }
    };

    const assertRevision = (revision: number): number => {
        if (!Number.isInteger(revision) || revision < 1) {
            throw new OmrWorkbenchInvalidError(
                'The expected OMR workbench revision must be a positive integer.',
                { code: 'revision_invalid' },
            );
        }

        if (
            latestProjection !== null &&
            revision !== latestProjection.revision
        ) {
            throw new OmrWorkbenchStaleError(
                'The expected OMR workbench revision is no longer current.',
                {
                    code: 'revision_stale',
                    details: {
                        expected: revision,
                        current: latestProjection.revision,
                    },
                },
            );
        }

        return revision;
    };

    const load = async (): Promise<OmrWorkbenchProjection> => {
        const showUrl = exactUrl(urls.show, 'show');
        return requestProjection(showUrl, {
            method: 'GET',
            fetchImpl,
            credentials,
            headers: config.headers,
            csrfToken: config.csrfToken,
            rememberProjection,
        });
    };

    const loadCaptureTarget = async (sheetId: OmrWorkbenchResourceId): Promise<OmrWorkbenchProjection> => {
        const url = new URL(exactUrl(urls.show, 'show'), window.location.origin);
        url.searchParams.set('capture_sheet_id', String(sheetId));
        return requestProjection(url.toString(), {
            method: 'GET',
            fetchImpl,
            credentials,
            headers: config.headers,
            csrfToken: config.csrfToken,
            rememberProjection,
        });
    };

    const reconcileCapture = async (
        operationId: string,
        idempotencyKey: string,
    ): Promise<OmrWorkbenchProjection> => {
        const url = new URL(exactUrl(urls.show, 'show'), window.location.origin);
        url.searchParams.set('capture_operation_id', operationId);
        url.searchParams.set('capture_idempotency_key', idempotencyKey);
        return requestProjection(url.toString(), {
            method: 'GET',
            fetchImpl,
            credentials,
            headers: config.headers,
            csrfToken: config.csrfToken,
            rememberProjection,
        });
    };

    const captureTransition = async (
        projectedUrl: string,
        payload: OmrWorkbenchCommandPayload,
        revision: number,
    ): Promise<OmrWorkbenchProjection> => requestProjection(
        exactUrl(projectedUrl, 'capture transition'),
        {
            method: 'POST',
            body: toJsonBody(withExpectedRevisionField(payload, assertRevision(revision), 'expected_revision')),
            fetchImpl,
            credentials,
            headers: config.headers,
            csrfToken: config.csrfToken,
            rememberProjection,
        },
    );

    const captureStart = async (
        input: OmrWorkbenchCaptureInput,
        payload: OmrWorkbenchCommandPayload,
        revision: number,
        authorization?: OmrCameraControllerAuthorization,
    ): Promise<OmrWorkbenchProjection> => {
        assertCommand(COMMAND_CAPTURE_START);
        const expectedRevision = assertRevision(revision);
        const captureUrl = exactUrl(urls.capture, 'capture');
        const capturePayload = withExpectedRevisionField(
            payload,
            expectedRevision,
            'expected_revision',
        );
        const body = toCaptureFormData(input, capturePayload);
        const headers = new Headers(config.headers);
        if (input.kind === 'staged') {
            if (
                !authorization?.token.trim() ||
                !Number.isSafeInteger(authorization.generation) ||
                authorization.generation < 1
            ) {
                throw new OmrWorkbenchInvalidError(
                    'The active desktop controller credential is required for a staged capture.',
                    { code: 'camera_authorization_required' },
                );
            }
            headers.set('X-Omr-Camera-Role', 'controller');
            headers.set('X-Omr-Camera-Token', authorization.token);
            headers.set('X-Omr-Camera-Generation', String(authorization.generation));
        }

        return requestProjection(captureUrl, {
            method: 'POST',
            body,
            fetchImpl,
            credentials,
            headers,
            csrfToken: config.csrfToken,
            rememberProjection,
        });
    };
    const captureRetry = async (
        captureId: OmrWorkbenchResourceId,
        payload: OmrWorkbenchCommandPayload,
        revision: number,
    ): Promise<OmrWorkbenchProjection> => {
        assertCommand(COMMAND_CAPTURE_RETRY);
        const expectedRevision = assertRevision(revision);
        const retryUrl = exactUrlForResource(urls.retry, 'retry', captureId);
        const retryPayload = withExpectedRevisionField(
            payload,
            expectedRevision,
            'expected_revision',
        );
        const body = toJsonBody(
            executionKind === 'diagnostic'
                ? withResourceId(retryPayload, 'capture_id', captureId)
                : withResourceId(retryPayload, 'sheet_id', captureId),
        );

        return requestProjection(retryUrl, {
            method: 'POST',
            body,
            fetchImpl,
            credentials,
            headers: config.headers,
            csrfToken: config.csrfToken,
            rememberProjection,
        });
    };

    const reviewSave = async (
        itemId: OmrWorkbenchResourceId,
        payload: OmrWorkbenchCommandPayload,
        revision: number,
    ): Promise<OmrWorkbenchProjection> => {
        assertCommand(COMMAND_REVIEW_SAVE);
        const expectedRevision = assertRevision(revision);
        const reviewPayload = withExpectedRevisionField(
            payload,
            expectedRevision,
            executionKind === 'diagnostic'
                ? 'expected_run_revision'
                : 'expected_revision',
        );
        const reviewBody =
            executionKind === 'diagnostic'
                ? withResourceId(reviewPayload, 'item_evidence_id', itemId)
                : withResourceId(reviewPayload, 'sheet_id', itemId);
        const reviewUrl = exactUrlForResource(urls.review, 'review', itemId);
        const body = toJsonBody(reviewBody);

        return requestProjection(reviewUrl, {
            method: 'POST',
            body,
            fetchImpl,
            credentials,
            headers: config.headers,
            csrfToken: config.csrfToken,
            rememberProjection,
        });
    };

    const resolveApplicant = async (
        sheetId: OmrWorkbenchResourceId,
        payload: OmrWorkbenchCommandPayload,
        revision: number,
    ): Promise<OmrWorkbenchProjection> => {
        assertKind(executionKind, 'production', 'resolveApplicant');
        assertCommand(COMMAND_RESOLVE_APPLICANT);
        const expectedRevision = assertRevision(revision);
        const resolvePayload = withResourceId(
            withExpectedRevisionField(
                payload,
                expectedRevision,
                'expected_revision',
            ),
            'sheet_id',
            sheetId,
        );
        const resolveUrl = exactUrlForResource(
            urls.resolveApplicant,
            'resolveApplicant',
            sheetId,
        );

        return requestProjection(resolveUrl, {
            method: 'POST',
            body: toJsonBody(resolvePayload),
            fetchImpl,
            credentials,
            headers: config.headers,
            csrfToken: config.csrfToken,
            rememberProjection,
        });
    };

    const resolveDuplicate = async (
        survivorSheetId: OmrWorkbenchResourceId,
        payload: OmrWorkbenchCommandPayload,
        revision: number,
    ): Promise<OmrWorkbenchProjection> => {
        assertKind(executionKind, 'production', 'resolveDuplicate');
        assertCommand(COMMAND_RESOLVE_DUPLICATE);
        const expectedRevision = assertRevision(revision);
        const resolvePayload = withResourceId(
            withExpectedRevisionField(
                payload,
                expectedRevision,
                'expected_revision',
            ),
            'survivor_sheet_id',
            survivorSheetId,
        );
        const resolveUrl = exactUrlForResource(
            urls.resolveDuplicate,
            'resolveDuplicate',
            survivorSheetId,
        );

        return requestProjection(resolveUrl, {
            method: 'POST',
            body: toJsonBody(resolvePayload),
            fetchImpl,
            credentials,
            headers: config.headers,
            csrfToken: config.csrfToken,
            rememberProjection,
        });
    };

    const diagnosticCompare = async (
        revision: number,
    ): Promise<OmrWorkbenchProjection> => {
        assertKind(executionKind, 'diagnostic', 'diagnosticCompare');
        assertCommand(COMMAND_DIAGNOSTIC_COMPARE);
        const expectedRevision = assertRevision(revision);
        const body = toJsonBody({
            expected_revision: expectedRevision,
            idempotency_key: newIdempotencyKey(),
        });
        const compareUrl = exactUrl(urls.compare, 'compare');

        return requestProjection(compareUrl, {
            method: 'POST',
            body,
            fetchImpl,
            credentials,
            headers: config.headers,
            csrfToken: config.csrfToken,
            rememberProjection,
        });
    };

    const diagnosticComplete = async (
        revision: number,
    ): Promise<OmrWorkbenchProjection> => {
        assertKind(executionKind, 'diagnostic', 'diagnosticComplete');
        assertCommand(COMMAND_DIAGNOSTIC_COMPLETE);
        const expectedRevision = assertRevision(revision);
        const body = toJsonBody({
            expected_revision: expectedRevision,
            idempotency_key: newIdempotencyKey(),
        });
        const completeUrl = exactUrl(urls.complete, 'complete');

        return requestProjection(completeUrl, {
            method: 'POST',
            body,
            fetchImpl,
            credentials,
            headers: config.headers,
            csrfToken: config.csrfToken,
            rememberProjection,
        });
    };
    const diagnosticCancel = async (
        payload: OmrWorkbenchCommandPayload,
        revision: number,
    ): Promise<OmrWorkbenchProjection> => {
        assertKind(executionKind, 'diagnostic', 'diagnosticCancel');
        assertCommand(COMMAND_DIAGNOSTIC_CANCEL);
        const expectedRevision = assertRevision(revision);
        const body = toJsonBody({
            ...payload,
            expected_revision: expectedRevision,
        });
        const cancelUrl = exactUrl(urls.cancel, 'cancel');

        return requestProjection(cancelUrl, {
            method: 'POST',
            body,
            fetchImpl,
            credentials,
            headers: config.headers,
            csrfToken: config.csrfToken,
            rememberProjection,
        });
    };

    const productionComplete = async (
        revision: number,
    ): Promise<OmrWorkbenchProjection> => {
        assertKind(executionKind, 'production', 'productionComplete');
        assertCommand(COMMAND_PRODUCTION_COMPLETE);
        const expectedRevision = assertRevision(revision);
        const body = toJsonBody({
            expected_revision: expectedRevision,
            idempotency_key: newIdempotencyKey(),
        });
        const completeUrl = exactUrl(urls.complete, 'complete');

        return requestProjection(completeUrl, {
            method: 'POST',
            body,
            fetchImpl,
            credentials,
            headers: config.headers,
            csrfToken: config.csrfToken,
            rememberProjection,
        });
    };

    return Object.freeze({
        workbenchId,
        executionKind,
        load,
        loadCaptureTarget,
        reconcileCapture,
        captureTransition,
        captureStart,
        captureRetry,
        reviewSave,
        resolveApplicant,
        resolveDuplicate,
        diagnosticCompare,
        diagnosticComplete,
        diagnosticCancel,
        productionComplete,
    });
}

type RequestProjectionOptions = {
    readonly method: 'GET' | 'POST';
    readonly body?: BodyInit;
    readonly fetchImpl: OmrWorkbenchFetch;
    readonly credentials: RequestCredentials;
    readonly headers?: HeadersInit;
    readonly csrfToken?: string;
    readonly rememberProjection: (value: unknown) => OmrWorkbenchProjection;
};

async function requestProjection(
    url: string,
    options: RequestProjectionOptions,
): Promise<OmrWorkbenchProjection> {
    const headers = new Headers(options.headers);
    const csrf = csrfHeaders(options.csrfToken ?? '');

    for (const [name, value] of Object.entries(csrf)) {
        headers.set(name, value);
    }

    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    if (options.body instanceof FormData) {
        headers.delete('Content-Type');
    } else if (options.body !== undefined) {
        headers.set('Content-Type', 'application/json');
    }

    let response: Response;

    try {
        response = await options.fetchImpl(url, {
            method: options.method,
            headers,
            credentials: options.credentials,
            body: options.body,
        });
    } catch (cause) {
        throw new OmrWorkbenchInvalidError(
            'The OMR workbench request outcome could not be confirmed.',
            { code: 'transport_unavailable', cause },
        );
    }

    let body: unknown;

    try {
        body = await readResponseBody(response);
    } catch (error) {
        if (!response.ok) {
            throw errorFromResponse(response.status, null);
        }

        throw error;
    }

    if (!response.ok) {
        throw errorFromResponse(response.status, body);
    }

    return options.rememberProjection(body);
}

async function readResponseBody(response: Response): Promise<unknown> {
    let text: string;

    try {
        text = await response.text();
    } catch (cause) {
        throw new OmrWorkbenchInvalidError(
            'The OMR workbench response could not be read.',
            { code: 'response_unreadable', status: response.status, cause },
        );
    }

    if (text.trim() === '') {
        return null;
    }

    try {
        return JSON.parse(text) as unknown;
    } catch (cause) {
        throw new OmrWorkbenchInvalidError(
            'The OMR workbench returned invalid JSON.',
            { code: 'response_json_invalid', status: response.status, cause },
        );
    }
}

function errorFromResponse(
    status: number,
    body: unknown,
): OmrWorkbenchClientError {
    const code = responseCode(status, body);
    const message = responseMessage(body, code);
    const kind = classifyError(status, code);
    const init: OmrWorkbenchErrorInit = {
        code,
        status,
        details: responseDetails(body),
    };

    switch (kind) {
        case 'stale':
            return new OmrWorkbenchStaleError(message, init);
        case 'denied':
            return new OmrWorkbenchDeniedError(message, init);
        case 'expired':
            return new OmrWorkbenchExpiredError(message, init);
        case 'conflict':
            return new OmrWorkbenchConflictError(message, init);
        case 'infrastructure':
            return new OmrWorkbenchInfrastructureError(message, init);
        case 'invalid':
        default:
            return new OmrWorkbenchInvalidError(message, init);
    }
}

function classifyError(status: number, code: string): OmrWorkbenchErrorKind {
    const normalized = code.toLowerCase();

    if (
        status === 410 ||
        status === 419 ||
        /(expired|expiration|continuation_timeout|timed_out|token_expired)/u.test(
            normalized,
        )
    ) {
        return 'expired';
    }

    if (
        status === 401 ||
        status === 403 ||
        /(denied|forbidden|unauthori[sz]ed|capability|not_allowed|not_projected)/u.test(
            normalized,
        )
    ) {
        return 'denied';
    }

    if (
        /(stale|revision|outdated|superseded|context_changed)/u.test(normalized)
    ) {
        return 'stale';
    }

    if (
        status === 409 ||
        /(conflict|concurren|duplicate|already_processed|version_mismatch)/u.test(
            normalized,
        )
    ) {
        return 'conflict';
    }

    if (status >= 500) {
        return 'infrastructure';
    }

    return 'invalid';
}

function responseCode(status: number, body: unknown): string {
    if (
        status === 422 &&
        isRecord(body) &&
        isRecord(body.errors) &&
        Object.keys(body.errors).length > 0
    ) {
        return 'form_request_validation_failed';
    }
    if (!isRecord(body)) {
        return 'workbench_request_failed';
    }

    const directCode = body.code;
    if (typeof directCode === 'string' && directCode.trim() !== '') {
        return directCode.trim();
    }

    const error = body.error;
    if (typeof error === 'string' && error.trim() !== '') {
        return error.trim();
    }

    if (
        isRecord(error) &&
        typeof error.code === 'string' &&
        error.code.trim() !== ''
    ) {
        return error.code.trim();
    }

    return 'workbench_request_failed';
}

function responseMessage(body: unknown, code: string): string {
    if (isRecord(body)) {
        if (typeof body.message === 'string' && body.message.trim() !== '') {
            return body.message;
        }

        if (typeof body.error === 'string' && body.error.trim() !== '') {
            return body.error;
        }

        if (isRecord(body.error) && typeof body.error.message === 'string') {
            return body.error.message;
        }
    }

    return `OMR workbench request failed (${code}).`;
}
function responseDetails(body: unknown): unknown {
    if (!isRecord(body)) return null;
    const raw = isRecord(body.details) ? body.details : {};
    const result: Record<string, string> = {};
    for (const key of ['stage', 'recovery', 'support_code'] as const) {
        const value = safeString(raw[key]);
        if (value !== null) result[key] = value;
    }
    return result;
}

function unwrapProjection(value: unknown): unknown {
    if (!isRecord(value)) {
        return value;
    }

    const projection = value.projection;
    return isRecord(projection) ? projection : value;
}

function assertWorkbenchId(value: string): string {
    if (typeof value !== 'string' || value.trim() === '') {
        throw new OmrWorkbenchInvalidError(
            'The OMR workbench identity must be a non-empty string.',
            { code: 'workbench_id_invalid' },
        );
    }

    return value;
}

function assertExecutionKind(value: OmrExecutionKind): OmrExecutionKind {
    if (value !== 'production' && value !== 'diagnostic') {
        throw new OmrWorkbenchInvalidError(
            'The OMR execution kind is unsupported.',
            { code: 'execution_kind_invalid' },
        );
    }

    return value;
}

function assertKind(
    actual: OmrExecutionKind,
    expected: OmrExecutionKind,
    method: string,
): void {
    if (actual !== expected) {
        throw new OmrWorkbenchDeniedError(
            `${method} is unavailable for the ${actual} OMR workbench.`,
            { code: 'execution_kind_mismatch' },
        );
    }
}

function exactUrl(value: string | undefined, method: string): string {
    if (typeof value !== 'string' || value.trim() === '') {
        throw new OmrWorkbenchInvalidError(
            `No URL is configured for ${method}.`,
            { code: 'url_not_configured' },
        );
    }

    return value;
}

function exactUrlForResource(
    value: OmrWorkbenchUrl | undefined,
    method: string,
    resourceId: OmrWorkbenchResourceId,
): string {
    if (typeof value === 'function') {
        const resolved = value(resourceId);
        return exactUrl(resolved, method);
    }

    return exactUrl(value, method);
}

function freezeUrls(
    value: OmrWorkbenchClientUrls,
): Readonly<OmrWorkbenchClientUrls> {
    if (value === null || typeof value !== 'object') {
        throw new OmrWorkbenchInvalidError(
            'The OMR workbench URL configuration is invalid.',
            { code: 'urls_invalid' },
        );
    }

    return Object.freeze({ ...value });
}

function withExpectedRevisionField(
    payload: OmrWorkbenchCommandPayload,
    revision: number,
    acceptedField: string,
): Record<string, unknown> {
    const normalized = normalizePayload(payload);
    assertNoRevisionAliases(normalized, acceptedField);
    assertPayloadRevision(normalized, acceptedField, revision);
    normalized[acceptedField] = revision;
    return normalized;
}

function withoutRevisionField(
    payload: OmrWorkbenchCommandPayload,
): Record<string, unknown> {
    const normalized = normalizePayload(payload);
    assertNoRevisionAliases(normalized, null);
    return normalized;
}

function assertNoRevisionAliases(
    payload: Record<string, unknown>,
    acceptedField: string | null,
): void {
    const revisionFields = [
        'expected_revision',
        'expected_run_revision',
        'expected_version',
        'expected_parent_version',
    ];

    for (const field of revisionFields) {
        if (
            field !== acceptedField &&
            Object.prototype.hasOwnProperty.call(payload, field)
        ) {
            throw new OmrWorkbenchInvalidError(
                `The OMR command cannot send ${field} for this endpoint.`,
                { code: 'revision_field_invalid', details: { field } },
            );
        }
    }
}

function withResourceId(
    payload: Record<string, unknown>,
    field: string,
    resourceId: OmrWorkbenchResourceId,
): Record<string, unknown> {
    const provided = payload[field];

    if (provided !== undefined && String(provided) !== String(resourceId)) {
        throw new OmrWorkbenchInvalidError(
            `The OMR command resource ${field} does not match the route.`,
            { code: 'resource_id_mismatch', details: { field } },
        );
    }

    return { ...payload, [field]: resourceId };
}

function normalizePayload(
    payload: OmrWorkbenchCommandPayload,
): Record<string, unknown> {
    if (!isRecord(payload) || Array.isArray(payload)) {
        throw new OmrWorkbenchInvalidError(
            'The OMR command payload must be a plain object.',
            { code: 'payload_invalid' },
        );
    }

    const normalized: Record<string, unknown> = { ...payload };

    for (const key of Object.keys(normalized)) {
        if (RESERVED_CONTEXT_FIELDS.has(key)) {
            throw new OmrWorkbenchInvalidError(
                `The OMR command payload cannot set ${key}.`,
                { code: 'payload_context_forbidden', details: { key } },
            );
        }
    }

    return normalized;
}

function assertPayloadRevision(
    payload: Record<string, unknown>,
    field: string,
    revision: number,
): void {
    const provided = payload[field];

    if (provided !== undefined && provided !== revision) {
        throw new OmrWorkbenchStaleError(
            'The command payload revision does not match the expected revision.',
            {
                code: 'payload_revision_stale',
                details: { field, provided, expected: revision },
            },
        );
    }
}

function toCaptureFormData(
    input: OmrWorkbenchCaptureInput,
    payload: Record<string, unknown>,
): FormData {
    const sourceFields = ['image', 'image_bytes', 'staged_frame_id', 'pairing_id', 'camera_command_id'];
    if (sourceFields.some((field) => Object.prototype.hasOwnProperty.call(payload, field))) {
        throw new OmrWorkbenchInvalidError(
            'The capture source must be supplied exactly once through the typed input.',
            { code: 'capture_source_duplicate' },
        );
    }
    const form = new FormData();
    if (input?.kind === 'raw') {
        if (!(typeof Blob !== 'undefined' && input.file instanceof Blob)) {
            throw new OmrWorkbenchInvalidError(
                'The local OMR capture must contain its original Blob or File.',
                { code: 'capture_file_invalid' },
            );
        }
        form.append('image', input.file);
    } else if (input?.kind === 'staged') {
        if ([input.stagedFrameId, input.pairingId, input.commandId].some((id) => typeof id !== 'string' || id.trim() === '')) {
            throw new OmrWorkbenchInvalidError(
                'The staged capture must identify its frame, pairing and command.',
                { code: 'capture_stage_invalid' },
            );
        }
        form.append('staged_frame_id', input.stagedFrameId);
        form.append('pairing_id', input.pairingId);
        form.append('camera_command_id', input.commandId);
    } else {
        throw new OmrWorkbenchInvalidError(
            'Choose one original-image capture source.',
            { code: 'capture_source_invalid' },
        );
    }

    for (const [key, value] of Object.entries(payload)) {
        if (value === undefined) {
            continue;
        }

        if (value === null) {
            form.append(key, '');
            continue;
        }

        if (typeof Blob !== 'undefined' && value instanceof Blob) {
            form.append(key, value);
            continue;
        }

        if (key === 'client_quad' && typeof value === 'object') {
            appendOmrClientQuadHint(form, value as OmrClientQuadHint);
            continue;
        }
        if (typeof value === 'object') {
            try {
                form.append(key, JSON.stringify(value));
            } catch (cause) {
                throw new OmrWorkbenchInvalidError(
                    `The OMR command field ${key} cannot be serialized.`,
                    { code: 'payload_serialization_failed', cause },
                );
            }
            continue;
        }

        form.append(key, String(value));
    }

    return form;
}

function toJsonBody(payload: Record<string, unknown>): string {
    try {
        return JSON.stringify(payload);
    } catch (cause) {
        throw new OmrWorkbenchInvalidError(
            'The OMR command payload cannot be serialized as JSON.',
            { code: 'payload_serialization_failed', cause },
        );
    }
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}
