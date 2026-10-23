import { createHash, randomUUID } from 'node:crypto';
import { execFile } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { mkdir, readFile, rename, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { promisify } from 'node:util';
import { expect, test, type Browser, type BrowserContext, type Page, type Request, type Response } from '@playwright/test';

const execFileAsync = promisify(execFile);
const compose = [
    'compose',
    '-p',
    process.env.COMPOSE_PROJECT_NAME || 'nexiam-document-recovery',
    '-f',
    'compose.yaml',
    '-f',
    'compose.document-recovery.yaml',
    '--profile',
    'document-recovery',
];
test.use({ channel: 'chromium' });

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const systemDir = path.resolve(__dirname, '../..');
const activeDrains = new Map<number, Promise<void>>();
const previewTimeoutMs = 30_000;
const queuedPreviewTimeoutMs = 120_000;
const caseTimeoutMs = 5 * 60 * 1000;

function launchQueueDrain(jobId: number): Promise<void> | undefined {
    if (!Number.isInteger(jobId) || jobId <= 0) return undefined;
    if (activeDrains.has(jobId)) return activeDrains.get(jobId);

    const drainPromise = (async () => {
        try {
            const { stdout, stderr } = await execFileAsync('docker', [
                ...compose,
                'exec',
                '-T',
                'document-recovery',
                'php',
                'bin/document-recovery.php',
                'drain',
                '--max-jobs=20',
                '--max-seconds=90',
            ], {
                cwd: systemDir,
                timeout: 115_000,
            });
            if (stdout) {
                console.log(`Preview queue drain completed for job ${jobId}:\n${stdout.trim()}`);
            }
            if (stderr) {
                console.warn(`Preview queue drain stderr for job ${jobId}:\n${stderr.trim()}`);
            }
        } catch (err) {
            console.error(`Preview queue drain failed for job ${jobId}:`, err);
        }
    })();
    activeDrains.set(jobId, drainPromise);
    return drainPromise;
}
const expectedCases = [
    'result-word',
    'result-builder',
    'passing-report-word',
    'course-quota-builder',
] as const;
type ExpectedCaseId = (typeof expectedCases)[number];
type CriterionStatus = 'PASS' | 'FAIL' | 'BLOCKED' | 'NOT_APPLICABLE' | 'PENDING';
type SourceKind = 'docx' | 'builder';
type Surface = 'result' | 'passing_report' | 'course_quota_qualifiers';

type FixtureCase = {
    id: ExpectedCaseId;
    surface: Surface;
    source_kind: SourceKind;
    marker: string;
    source_sha256: string;
    builder_sha256?: string;
    actor: { id: number; email: string; password: string };
    template: { id: number; name: string; is_active: false; list_url: string };
    libreoffice_available: boolean;
};

type FixtureManifest = {
    schema_version: 2;
    run_id: string;
    service: 'document-recovery';
    public_port: number;
    source_digest: string;
    build_digest: string;
    cases: FixtureCase[];
};

type CriterionLedger = Record<string, CriterionStatus>;
type PendingCapture = Promise<void>;
type CaptureOutcome = 'PASS' | 'PENDING' | 'FAIL' | 'BLOCKED';
type AttemptLedger = { attempt: number; outcome: CaptureOutcome; criteria: CriterionLedger; state: Record<string, unknown>; errors: string[] };
type Capture = {
    requests: RequestLedger[];
    responses: CapturedResponse[];
    previewResponses: CapturedResponse[];
    iframeResponses: CapturedResponse[];
    statusResponses: CapturedResponse[];
    downloadResponses: CapturedResponse[];
    draftResponses: CapturedResponse[];
    printStatusTimeline: Array<Record<string, unknown>>;
    consoleErrors: string[];
    pageErrors: Array<{ at: string; error: string }>;
    requestFailures: Array<Record<string, unknown>>;
    blockedForeignRequests: string[];
    servedAssets: Map<string, Record<string, unknown>>;
    assetChanges: Array<Record<string, unknown>>;
    drain: () => Promise<void>;
};

type CapturedResponse = {
    request_id: number;
    request_at: string;
    response_at: string;
    url: string;
    pathname: string;
    method: string;
    resource_type: string;
    status: number;
    status_text: string;
    content_type: string;
    body_sha256: string | null;
    body_bytes: number | null;
    body_file: string | null;
    parsed_json?: Record<string, unknown>;
    pdf_file?: string;
    pdf_sha256?: string;
    pdf_bytes?: number;
    error?: string;
};

type RequestLedger = {
    request_id: number;
    at: string;
    url: string;
    pathname: string;
    method: string;
    resource_type: string;
    body_sha256: string | null;
    draft_state_sha256?: string;
    preview_input_sha256?: string;
};

type CaseLedger = {
    run_id: string;
    started_at: string;
    id: string;
    surface: Surface;
    source_kind: SourceKind;
    template_id: number;
    actor_id: number | null;
    marker: string;
    outcome: CaptureOutcome;
    criteria: CriterionLedger;
    attempt_results: AttemptLedger[];
    evidence_dir: string;
    requests: RequestLedger[];
    responses: CapturedResponse[];
    draft_responses: CapturedResponse[];
    print_status_timeline: Array<Record<string, unknown>>;
    console_errors: string[];
    page_errors: Array<{ at: string; error: string }>;
    request_failures: Array<Record<string, unknown>>;
    blocked_foreign_requests: string[];
    served_assets: Array<Record<string, unknown>>;
    screenshots: string[];
    snapshot_files: string[];
    errors: string[];
    state: Record<string, unknown>;
};

function requiredEnvironment(name: string): string {
    const value = process.env[name];
    if (!value) throw new Error(`${name} is required for the isolated capture.`);
    return value;
}

function sha256(value: Buffer | string): string {
    return createHash('sha256').update(value).digest('hex');
}

function canonicalJson(value: unknown): string {
    if (Array.isArray(value)) return `[${value.map(canonicalJson).join(',')}]`;
    if (value !== null && typeof value === 'object') {
        const entries = Object.entries(value as Record<string, unknown>).sort(([left], [right]) => left.localeCompare(right));
        return `{${entries.map(([key, item]) => `${JSON.stringify(key)}:${canonicalJson(item)}`).join(',')}}`;
    }
    return JSON.stringify(value);
}

function outcomeFor(criteria: CriterionLedger, errors: string[]): CaptureOutcome {
    if (Object.values(criteria).some((status) => status === 'FAIL')) return 'FAIL';
    if (errors.length > 0) return 'BLOCKED';
    if (Object.values(criteria).every((status) => status === 'PASS' || status === 'NOT_APPLICABLE')) return 'PASS';
    if (criteria.pdf_bytes_hash_marker === 'PENDING'
        && Object.entries(criteria).every(([key, status]) => key === 'pdf_bytes_hash_marker'
            ? status === 'PENDING'
            : status === 'PASS' || status === 'NOT_APPLICABLE')) {
        return 'PENDING';
    }
    return 'BLOCKED';
}

function hasAttemptPdfEvidence(fixtureCase: CaseLedger, attempt: AttemptLedger): boolean {
    if (fixtureCase.id === 'result-word') {
        const iframeResponse = attempt.state.iframe_http_response;
        return attempt.state.downloaded_pdf_signature === true
            && typeof attempt.state.current_pdf_artifact === 'string'
            && attempt.state.current_pdf_artifact.length > 0
            && attempt.state.iframe_http_checksum_pass === true
            && typeof iframeResponse === 'object'
            && iframeResponse !== null
            && typeof (iframeResponse as Record<string, unknown>).pdf_file === 'string'
            && /^[a-f0-9]{64}$/i.test(String((iframeResponse as Record<string, unknown>).pdf_sha256 ?? ''));
    }
    const iframePdf = attempt.state.iframe_pdf;
    return typeof iframePdf === 'object'
        && iframePdf !== null
        && typeof (iframePdf as Record<string, unknown>).artifact === 'string'
        && String((iframePdf as Record<string, unknown>).artifact).length > 0
        && /^[a-f0-9]{64}$/i.test(String((iframePdf as Record<string, unknown>).sha256 ?? ''));
}

function canDeferPdfRaster(ledgers: CaseLedger[], assetChanges: Array<Record<string, unknown>>): boolean {
    if (assetChanges.length > 0
        || ledgers.length !== expectedCases.length
        || expectedCases.some((id, index) => ledgers[index]?.id !== id)) {
        return false;
    }
    let hasPendingPdf = false;
    return ledgers.every((ledger) => {
        if (ledger.errors.length > 0
            || (ledger.outcome !== 'PASS' && ledger.outcome !== 'PENDING')
            || (ledger.outcome === 'PENDING' && outcomeFor(ledger.criteria, ledger.errors) !== 'PENDING')) {
            return false;
        }
        if (ledger.criteria.pdf_bytes_hash_marker === 'PENDING') hasPendingPdf = true;
        if (!Object.entries(ledger.criteria).every(([key, status]) => key === 'pdf_bytes_hash_marker'
            ? status === 'PENDING' || status === 'PASS'
            : status === 'PASS' || status === 'NOT_APPLICABLE')) {
            return false;
        }
        return ledger.attempt_results.length === 2
            && ledger.attempt_results.every((attempt, index) => attempt.attempt === index + 1
                && attempt.errors.length === 0
                && (attempt.outcome === 'PASS' || attempt.outcome === 'PENDING')
                && (attempt.outcome !== 'PENDING' || outcomeFor(attempt.criteria, attempt.errors) === 'PENDING')
                && Object.entries(attempt.criteria).every(([key, status]) => key === 'pdf_bytes_hash_marker'
                    ? status === 'PENDING' || status === 'PASS'
                    : status === 'PASS' || status === 'NOT_APPLICABLE')
                && hasAttemptPdfEvidence(ledger, attempt));
    }) && hasPendingPdf;
}

function redact(value: string, fixtureCase: FixtureCase): string {
    let safe = value;
    if (fixtureCase.actor.password) safe = safe.split(fixtureCase.actor.password).join('[REDACTED]');
    if (fixtureCase.actor.email) safe = safe.split(fixtureCase.actor.email).join('[SYNTHETIC ACTOR]');
    return safe;
}

function relativePath(root: string, child: string): string {
    return path.relative(root, child).split(path.sep).join('/');
}

async function writeJson(filePath: string, value: unknown): Promise<void> {
    await mkdir(path.dirname(filePath), { recursive: true, mode: 0o700 });
    const temporaryPath = `${filePath}.${randomUUID()}.tmp`;
    await writeFile(temporaryPath, `${JSON.stringify(value, null, 2)}\n`, { mode: 0o600, flag: 'wx' });
    await rename(temporaryPath, filePath);
}

function validateManifest(manifest: FixtureManifest, runId: string): FixtureCase[] {
    if (manifest?.schema_version !== 2
        || manifest.run_id !== runId
        || manifest.service !== 'document-recovery'
        || !Number.isInteger(manifest.public_port)
        || !/^[a-f0-9]{64}$/i.test(manifest.source_digest)
        || !/^[a-f0-9]{64}$/i.test(manifest.build_digest)
        || !Array.isArray(manifest.cases)) {
        throw new Error('The isolated fixture manifest has an unsupported schema, missing capture identity, or mismatched run identity.');
    }
    const byId = new Map(manifest.cases.map((item) => [item.id, item]));
    if (manifest.cases.length !== expectedCases.length || expectedCases.some((id) => !byId.has(id))) {
        throw new Error('The fixture manifest must contain the four agreed cases exactly once.');
    }
    const expected: Record<ExpectedCaseId, [Surface, SourceKind]> = {
        'result-word': ['result', 'docx'],
        'result-builder': ['result', 'builder'],
        'passing-report-word': ['passing_report', 'docx'],
        'course-quota-builder': ['course_quota_qualifiers', 'builder'],
    };
    const ordered = expectedCases.map((id) => byId.get(id)!);
    for (const fixtureCase of ordered) {
        const [surface, sourceKind] = expected[fixtureCase.id];
        if (fixtureCase.surface !== surface || fixtureCase.source_kind !== sourceKind) {
            throw new Error(`Fixture case [${fixtureCase.id}] has the wrong surface/source_kind.`);
        }
        if (!fixtureCase.marker
            || !Number.isInteger(fixtureCase.actor?.id)
            || !fixtureCase.actor.email
            || !fixtureCase.actor.password
            || typeof fixtureCase.libreoffice_available !== 'boolean') {
            throw new Error(`Fixture case [${fixtureCase.id}] lacks a marker, synthetic staff identity, or converter availability result.`);
        }
        if (!Number.isInteger(fixtureCase.template?.id) || !fixtureCase.template.name || fixtureCase.template.is_active !== false) {
            throw new Error(`Fixture case [${fixtureCase.id}] must name an existing inactive template.`);
        }
        if (!/^[a-f0-9]{64}$/i.test(fixtureCase.source_sha256)) {
            throw new Error(`Fixture case [${fixtureCase.id}] has no valid source checksum.`);
        }
        if (sourceKind === 'builder' && !/^[a-f0-9]{64}$/i.test(fixtureCase.builder_sha256 ?? '')) {
            throw new Error(`Fixture case [${fixtureCase.id}] must include the canonical builder_sha256.`);
        }
        const expectedListUrl = surface === 'result' ? '/setup/result-templates' : `/setup/report-templates/${surface}`;
        if (fixtureCase.template.list_url !== expectedListUrl) {
            throw new Error(`Fixture case [${fixtureCase.id}] must use the existing list route ${expectedListUrl}.`);
        }
    }
    return ordered;
}

function validateOrigin(baseUrl: string): URL {
    const url = new URL(baseUrl);
    if (url.protocol !== 'http:' || url.hostname !== '127.0.0.1' || url.port === '18000' || !url.port) {
        throw new Error('The capture may use only its explicit 127.0.0.1 relay URL, never port 18000.');
    }
    return url;
}

function createCapture(page: Page, fixtureCase: FixtureCase, caseDir: string, baseUrl: URL): Capture {
    let requestId = 0;
    const requests: RequestLedger[] = [];
    const requestRows = new Map<Request, RequestLedger>();
    const responses: CapturedResponse[] = [];
    const printStatusTimeline: Array<Record<string, unknown>> = [];
    const consoleErrors: string[] = [];
    const pageErrors: Array<{ at: string; error: string }> = [];
    const requestFailures: Array<Record<string, unknown>> = [];
    const blockedForeignRequests: string[] = [];
    const servedAssets = new Map<string, Record<string, unknown>>();
    const assetChanges: Array<Record<string, unknown>> = [];
    const pending = new Set<PendingCapture>();
    const previewResponses: CapturedResponse[] = [];
    const iframeResponses: CapturedResponse[] = [];
    const statusResponses: CapturedResponse[] = [];
    const downloadResponses: CapturedResponse[] = [];
    const draftResponses: CapturedResponse[] = [];

    function track(task: PendingCapture): void {
        pending.add(task);
        void task.finally(() => pending.delete(task)).catch(() => undefined);
    }

    page.on('console', (message) => {
        if (message.type() === 'error' || message.type() === 'warning') {
            consoleErrors.push(`${new Date().toISOString()} ${redact(`${message.type()}: ${message.text()}`, fixtureCase)}`);
        }
    });
    page.on('pageerror', (error) => pageErrors.push({ at: new Date().toISOString(), error: redact(error.stack ?? error.message, fixtureCase) }));
    page.on('request', (request) => {
        const requestUrl = new URL(request.url());
        if (['data:', 'blob:', 'chrome-extension:', 'about:'].includes(requestUrl.protocol)) return;
        if (requestUrl.origin !== baseUrl.origin) {
            blockedForeignRequests.push(`${request.method()} ${requestUrl.origin}${requestUrl.pathname}`);
            return;
        }
        const rawBody = request.postDataBuffer();
        let draftStateSha256: string | undefined;
        let previewInputSha256: string | undefined;
        if (/\/drafts(?:\/|$)/.test(requestUrl.pathname) && rawBody) {
            try {
                const parsed = JSON.parse(rawBody.toString('utf8')) as Record<string, unknown>;
                if ('state' in parsed) draftStateSha256 = sha256(canonicalJson(parsed.state));
            } catch {
                draftStateSha256 = sha256(rawBody);
            }
        }
        if (requestUrl.pathname.endsWith('/preview') && rawBody) previewInputSha256 = sha256(rawBody);
        const row: RequestLedger = {
            request_id: ++requestId,
            at: new Date().toISOString(),
            url: `${requestUrl.origin}${requestUrl.pathname}`,
            pathname: requestUrl.pathname,
            method: request.method(),
            resource_type: request.resourceType(),
            body_sha256: rawBody ? sha256(rawBody) : null,
            ...(draftStateSha256 ? { draft_state_sha256: draftStateSha256 } : {}),
            ...(previewInputSha256 ? { preview_input_sha256: previewInputSha256 } : {}),
        };
        requests.push(row);
        requestRows.set(request, row);
    });
    page.on('requestfailed', (request) => {
        requestFailures.push({
            at: new Date().toISOString(),
            method: request.method(),
            url: `${new URL(request.url()).origin}${new URL(request.url()).pathname}`,
            resource_type: request.resourceType(),
            error: redact(request.failure()?.errorText ?? 'request failed', fixtureCase),
        });
    });
    page.on('response', (response) => {
        track(captureResponse(response));
    });

    async function captureResponse(response: Response): Promise<void> {
        const request = response.request();
        const requestRow = requestRows.get(request);
        const requestUrl = new URL(response.url());
        const pathname = requestUrl.pathname;
        const contentType = (await response.headerValue('content-type')) ?? '';
        const isPreview = pathname.endsWith('/preview');
        const isDraft = /\/drafts(?:\/|$)/.test(pathname);
        const isStatus = /^\/printing\/jobs\/\d+$/.test(pathname);
        const isDownload = /^\/printing\/jobs\/\d+\/download$/.test(pathname);
        const isThumbnail = pathname.endsWith('/thumbnail');
        const isAsset = /\.(?:m?js|css)$/.test(pathname) || request.resourceType() === 'script' || request.resourceType() === 'stylesheet';
        if (!isPreview && !isDraft && !isStatus && !isDownload && !isThumbnail && !isAsset) return;

        const record: CapturedResponse = {
            request_id: requestRow?.request_id ?? -1,
            request_at: requestRow?.at ?? '',
            response_at: new Date().toISOString(),
            url: `${requestUrl.origin}${pathname}`,
            pathname,
            method: request.method(),
            resource_type: request.resourceType(),
            status: response.status(),
            status_text: response.statusText(),
            content_type: contentType,
            body_sha256: null,
            body_bytes: null,
            body_file: null,
        };
        const statusTimelineRecord = isStatus
            ? {
                at: record.response_at,
                request_id: record.request_id,
                http_status: response.status(),
                content_type: contentType,
                job_status: null,
                safe_error: null,
            }
            : null;
        if (statusTimelineRecord) printStatusTimeline.push(statusTimelineRecord);
        try {
            const body = await Promise.race([
                response.body(),
                new Promise<Buffer>((_, reject) => setTimeout(() => reject(new Error('response.body() timed out')), 5000)),
            ]);
            record.body_sha256 = sha256(body);
            record.body_bytes = body.length;
            const responseStem = `${String(record.request_id).padStart(3, '0')}-${String(record.status)}`;
            if (isPreview || isDraft || isStatus || (isDownload && request.resourceType() === 'document')) {
                const extension = contentType.includes('json') ? 'json' : contentType.includes('pdf') ? 'pdf' : 'bin';
                const directory = path.join(caseDir, 'transport');
                await mkdir(directory, { recursive: true, mode: 0o700 });
                const bodyFile = path.join(directory, `${responseStem}-${isPreview ? 'preview' : isDraft ? 'draft' : isStatus ? 'status' : 'iframe'}.${extension}`);
                await writeFile(bodyFile, body, { mode: 0o600 });
                record.body_file = relativePath(caseDir, bodyFile);
                const isPdfResponse = contentType.toLowerCase().includes('application/pdf');
                const isEmbedderHtml = isPdfResponse && body.subarray(0, 32).toString('utf8').toLowerCase().startsWith('<!doctype html>');
                if (isDownload && isPdfResponse) {
                    if (isEmbedderHtml) {
                        record.pdf_file = null;
                        record.pdf_sha256 = null;
                        record.pdf_bytes = null;
                        record.embedder_html = true;
                    } else {
                        record.pdf_file = record.body_file;
                        record.pdf_sha256 = record.body_sha256;
                        record.pdf_bytes = record.body_bytes;
                    }
                    iframeResponses.push(record);
                }
            }

            if (contentType.toLowerCase().includes('json')) {
                try {
                    const parsed = JSON.parse(body.toString('utf8')) as Record<string, unknown>;
                    const parsedSummary: Record<string, unknown> = {};
                    if (isPreview) {
                        Object.assign(parsedSummary, {
                            preview_job_id: parsed.preview_job_id ?? null,
                            preview_url: parsed.preview_url ?? null,
                            source_checksum: parsed.source_checksum ?? null,
                            error: parsed.error ?? null,
                            diagnostic_count: Array.isArray(parsed.diagnostics) ? parsed.diagnostics.length : 0,
                            has_pdf_base64: typeof parsed.pdf_base64 === 'string',
                        });
                        const previewJobId = Number(parsed.preview_job_id ?? 0);
                        if (Number.isInteger(previewJobId) && previewJobId > 0 && !activeDrains.has(previewJobId)) {
                            launchQueueDrain(previewJobId);
                        }
                        if (typeof parsed.pdf_base64 === 'string') {
                            const pdfBytes = decodePdf(parsed.pdf_base64);
                            const pdfPath = path.join(caseDir, 'transport', `${responseStem}-preview.pdf`);
                            await writeFile(pdfPath, pdfBytes, { mode: 0o600 });
                            record.pdf_file = relativePath(caseDir, pdfPath);
                            record.pdf_sha256 = sha256(pdfBytes);
                            record.pdf_bytes = pdfBytes.length;
                        }
                    } else if (isStatus) {
                        Object.assign(parsedSummary, {
                            id: parsed.id ?? null,
                            status: parsed.status ?? null,
                            ready: parsed.ready ?? null,
                            safe_error: parsed.safe_error ?? null,
                            progress_done: parsed.progress_done ?? null,
                            progress_total: parsed.progress_total ?? null,
                        });
                        if (statusTimelineRecord) {
                            statusTimelineRecord.job_status = parsed.status ?? null;
                            statusTimelineRecord.safe_error = parsed.safe_error ?? null;
                        }
                        const jobId = Number(parsed.id ?? pathname.replace('/printing/jobs/', ''));
                        if (parsed.status === 'queued' && Number.isInteger(jobId) && jobId > 0 && !activeDrains.has(jobId)) {
                            launchQueueDrain(jobId);
                        }
                    } else if (isDraft && typeof parsed.draft === 'object' && parsed.draft !== null) {
                        const draft = parsed.draft as Record<string, unknown>;
                        parsedSummary.draft = {
                            id: draft.id ?? null,
                            stage: draft.stage ?? null,
                            lock_version: draft.lock_version ?? null,
                            source_template_id: draft.source_template_id ?? null,
                        };
                        parsedSummary.error = parsed.error ?? null;
                    }
                    record.parsed_json = parsedSummary;
                } catch (error) {
                    record.error = `response JSON parse: ${redact(String(error), fixtureCase)}`;
                }
            }
            responses.push(record);
            if (isPreview) previewResponses.push(record);
            if (isStatus) statusResponses.push(record);
            if (isDraft) draftResponses.push(record);
            if (isDownload && request.resourceType() === 'document') downloadResponses.push(record);

            if (isThumbnail) {
                const key = `${requestUrl.origin}${pathname}`;
                const entry = { url: key, status: response.status(), content_type: contentType, bytes: body.length, sha256: record.body_sha256 };
                const previous = servedAssets.get(key);
                if (previous && previous.sha256 !== entry.sha256) assetChanges.push({ url: key, before: previous.sha256, after: entry.sha256 });
                servedAssets.set(key, entry);
            } else if (isAsset) {
                const key = `${requestUrl.origin}${pathname}`;
                const entry = { url: key, status: response.status(), content_type: contentType, bytes: body.length, sha256: record.body_sha256 };
                const previous = servedAssets.get(key);
                if (previous && previous.sha256 !== entry.sha256) assetChanges.push({ url: key, before: previous.sha256, after: entry.sha256 });
                servedAssets.set(key, entry);
            }
        } catch (error) {
            record.error = redact(`response body unavailable: ${String(error)}`, fixtureCase);
            responses.push(record);
            if (isPreview) previewResponses.push(record);
            if (isStatus) statusResponses.push(record);
            if (isDraft) draftResponses.push(record);
        }
    }

    async function drain(): Promise<void> {
        if (pending.size === 0) return;
        const current = [...pending];
        await Promise.race([
            Promise.allSettled(current),
            new Promise((resolve) => setTimeout(resolve, 2000)),
        ]);
    }

    return {
        requests,
        responses,
        previewResponses,
        iframeResponses,
        statusResponses,
        downloadResponses,
        draftResponses,
        printStatusTimeline,
        consoleErrors,
        pageErrors,
        requestFailures,
        blockedForeignRequests,
        servedAssets,
        assetChanges,
        drain,
    };
}

function decodePdf(value: string): Buffer {
    if (value.length === 0 || value.length % 4 !== 0 || !/^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$/.test(value)) {
        throw new Error('Preview JSON did not contain canonical base64.');
    }
    const bytes = Buffer.from(value, 'base64');
    if (!bytes.length || !bytes.subarray(0, 5).equals(Buffer.from('%PDF-'))) {
        throw new Error('Decoded preview response is not a PDF.');
    }
    return bytes;
}


async function readOnlySnapshot(fixtureCase: FixtureCase, jobId: number | null, targetPath: string): Promise<Record<string, unknown>> {
    const fixture = {
        id: fixtureCase.id,
        surface: fixtureCase.surface,
        template_id: fixtureCase.template.id,
        actor_id: fixtureCase.actor.id ?? null,
        actor: { email: fixtureCase.actor.email },
    };
    const encodedCase = Buffer.from(JSON.stringify(fixture)).toString('base64');
    const php = [
        "require 'vendor/autoload.php';",
        "$app = require 'bootstrap/app.php';",
        '$kernel = $app->make(\\Illuminate\\Contracts\\Console\\Kernel::class);',
        '$kernel->bootstrap();',
        "require 'tests/Support/DocumentPreviewTransportFixture.php';",
        `$snapshot = \\Tests\\Support\\DocumentPreviewTransportFixture::readOnlySnapshot(json_decode(base64_decode('${encodedCase}'), true, 512, JSON_THROW_ON_ERROR), ${jobId ?? 'null'});`,
        'echo json_encode($snapshot, JSON_THROW_ON_ERROR);',
    ].join(' ');
    const { stdout } = await execFileAsync('docker', [
        ...compose,
        'exec',
        '-T',
        'document-recovery',
        'php',
        '-r',
        php,
    ], {
        cwd: path.resolve(process.cwd()),
        maxBuffer: 16 * 1024 * 1024,
        timeout: 30_000,
    });
    const snapshot = JSON.parse(stdout.trim()) as Record<string, unknown>;
    await writeJson(targetPath, snapshot);
    return snapshot;
}

function snapshotTemplate(snapshot: Record<string, unknown>): Record<string, unknown> | null {
    return typeof snapshot.template === 'object' && snapshot.template !== null
        ? snapshot.template as Record<string, unknown>
        : null;
}

function sourceHashFromSnapshot(snapshot: Record<string, unknown>, fixtureCase: FixtureCase): string | null {
    const template = snapshotTemplate(snapshot);
    if (!template) return null;
    const value = fixtureCase.source_kind === 'docx'
        ? template.source_bytes_sha256
        : template.template_checksum;
    return typeof value === 'string' ? value : null;
}

function templateChecksumFromSnapshot(snapshot: Record<string, unknown>): string | null {
    const template = snapshotTemplate(snapshot);
    return typeof template?.template_checksum === 'string' ? template.template_checksum : null;
}

async function currentStage(page: Page): Promise<string | null> {
    return page.locator('[data-result-sheet-workflow-stage][aria-current="step"]').getAttribute('data-result-sheet-workflow-stage');
}

async function findTemplateRow(page: Page, fixtureCase: FixtureCase) {
    const row = page.locator('article').filter({ hasText: fixtureCase.template.name }).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    return row;
}

async function readIndexPng(page: Page, fixtureCase: FixtureCase, targetPath: string): Promise<Record<string, unknown>> {
    const row = await findTemplateRow(page, fixtureCase);
    const image = row.locator('img').first();
    await expect(image).toBeVisible({ timeout: 20_000 });
    await expect.poll(() => image.evaluate((element: HTMLImageElement) => element.complete && element.naturalWidth > 0), { timeout: 20_000 }).toBe(true);
    const src = await image.evaluate((element: HTMLImageElement) => element.currentSrc || element.src);
    const width = await image.evaluate((element: HTMLImageElement) => element.naturalWidth);
    const result = await page.evaluate(async (url) => {
        const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
        const blob = await response.blob();
        const base64 = await new Promise<string>((resolve, reject) => {
            const reader = new FileReader();
            reader.onerror = () => reject(new Error('Could not read thumbnail response.'));
            reader.onload = () => resolve(String(reader.result).split(',')[1] ?? '');
            reader.readAsDataURL(blob);
        });
        return { status: response.status, contentType: response.headers.get('content-type') ?? '', bytes: blob.size, base64 };
    }, src);
    const bytes = Buffer.from(result.base64, 'base64');
    const signature = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
    await writeFile(targetPath, bytes, { mode: 0o600 });
    return {
        url: new URL(src).pathname,
        status: result.status,
        content_type: result.contentType,
        response_bytes: result.bytes,
        natural_width: width,
        png_signature: bytes.subarray(0, signature.length).equals(signature),
        sha256: sha256(bytes),
        artifact: targetPath,
    };
}

async function openExistingEdit(page: Page, fixtureCase: FixtureCase, capture: Capture): Promise<void> {
    const baseUrl = validateOrigin(requiredEnvironment('DOCUMENT_PREVIEW_BASE_URL'));
    const listUrl = new URL(fixtureCase.template.list_url, baseUrl).toString();
    await page.goto(listUrl, { waitUntil: 'domcontentloaded' });
    const row = await findTemplateRow(page, fixtureCase);
    await expect(row.getByText('Inactive', { exact: true })).toBeVisible();
    const expectedPath = fixtureCase.surface === 'result'
        ? `/setup/result-templates/${fixtureCase.template.id}/edit`
        : `/setup/report-templates/${fixtureCase.surface}/${fixtureCase.template.id}/edit`;
    await row.getByRole('link', { name: /edit/i }).or(row.getByRole('button', { name: /edit/i })).first().click();
    await page.waitForURL((url) => url.pathname === expectedPath, { timeout: 20_000 });
    await expect(page.locator('[data-result-sheet-workflow]')).toBeVisible({ timeout: 15_000 });
    await capture.drain();
}

async function advanceThroughStages(page: Page, fixtureCase: FixtureCase, attempt: number, caseDir: string, capture: Capture): Promise<Record<string, unknown>> {
    const stages: [string, string, string] = fixtureCase.source_kind === 'docx'
        ? ['copy', 'upload', 'preview']
        : ['setup', 'design', 'verify'];
    const stageContentMarker: Record<string, string> = {
        copy: 'word-copy',
        upload: 'word-upload',
        setup: 'setup',
        design: 'design',
        preview: 'verify',
        verify: 'verify',
    };
    const attemptDir = path.join(caseDir, `attempt-${attempt}`);
    await mkdir(attemptDir, { recursive: true, mode: 0o700 });
    const startStage = await currentStage(page);
    if (startStage !== stages[0]) {
        if (fixtureCase.source_kind === 'docx' && startStage === 'upload') {
            return {
                start_stage: startStage,
                resumed_word_draft_guard: true,
                stage: startStage,
                returned_upload_state: true,
                automatic_preview: false,
                iframe_visible: false,
                save_disabled: true,
                preview_error: 'Resumed Word draft earlier stage guard: editor returned to upload stage; document reselection required. Guard preserved without bypass.',
                paint: { status: 'BLOCKED', reason: 'Resumed Word draft requires file re-selection; stage 3 preview was not entered.' },
            };
        }
        throw new Error(`Expected Stage 1 [${stages[0]}], found [${startStage ?? 'none'}].`);
    }
    let stage3EnteredAt = '';
    let stage3NavigationStartedAt = '';
    let stage3RequestCountAtEntry = 0;
    for (let stageIndex = 1; stageIndex < stages.length; stageIndex += 1) {
        const stageId = stages[stageIndex];
        if (stageIndex === 2) {
            stage3NavigationStartedAt = new Date().toISOString();
            stage3RequestCountAtEntry = capture.previewResponses.length;
        }
        await page.locator(`[data-result-sheet-workflow-stage="${stageId}"]`).click();
        await expect(page.locator(`[data-result-sheet-stage="${stageContentMarker[stageId]}"]`)).toBeVisible({ timeout: 15_000 });
        if (stageIndex === 2) stage3EnteredAt = new Date().toISOString();
        await capture.drain();
    }

    const activeStage = await currentStage(page);
    if (activeStage !== stages[2]) throw new Error(`Stage 3 did not become current; found [${activeStage ?? 'none'}].`);
    const stage3Root = page.locator('[data-result-sheet-stage="verify"]');
    const refreshButtonPresent = await page.getByRole('button', { name: 'Refresh', exact: true }).last().isVisible().catch(() => false);

    const isQueuedCase = fixtureCase.source_kind === 'docx';
    const effectiveTimeoutMs = isQueuedCase ? queuedPreviewTimeoutMs : previewTimeoutMs;

    let previewWaitError: string | null = null;
    try {
        await page.waitForFunction(() => {
            const iframe = document.querySelector('iframe[title="Template PDF preview"]');
            const error = document.querySelector('[data-result-sheet-stage="verify"] [role="alert"]')
                ?? document.querySelector('[role="alert"]');
            return Boolean(iframe || error);
        }, undefined, { timeout: effectiveTimeoutMs });
    } catch (error) {
        previewWaitError = String(error);
    }
    await capture.drain();

    const previewForDrain = [...capture.previewResponses].sort((left, right) => left.request_id - right.request_id).at(-1);
    const drainJobId = Number(previewForDrain?.parsed_json?.preview_job_id ?? 0);
    if (drainJobId > 0 && activeDrains.has(drainJobId)) {
        await activeDrains.get(drainJobId);
    } else {
        for (const promise of activeDrains.values()) {
            await promise;
        }
    }

    const iframe = page.locator('iframe[title="Template PDF preview"]');
    let iframeVisible = await iframe.isVisible().catch(() => false);
    if (!iframeVisible && isQueuedCase) {
        try {
            await page.waitForFunction(() => {
                const iframeElement = document.querySelector('iframe[title="Template PDF preview"]');
                return Boolean(iframeElement);
            }, undefined, { timeout: 15_000 });
            iframeVisible = await iframe.isVisible().catch(() => false);
            if (iframeVisible) {
                previewWaitError = null;
            }
        } catch {
            // retain previewWaitError
        }
    }
    const iframeSrc = iframeVisible ? await iframe.getAttribute('src') : null;
    const alertText = await stage3Root.locator('[role="alert"]').allTextContents().catch(() => []);
    const stageText = await stage3Root.innerText().catch(() => '');
    const saveButton = page.getByRole('button', { name: /^(Save changes|Save)$/ }).last();
    const saveDisabled = await saveButton.isDisabled().catch(() => true);
    const previewResponses = [...capture.previewResponses].sort((left, right) => left.request_id - right.request_id);
    const latestPreview = previewResponses.at(-1) ?? null;
    const previewJson = latestPreview?.parsed_json ?? null;
    const baseUrl = validateOrigin(requiredEnvironment('DOCUMENT_PREVIEW_BASE_URL'));
    let displayedPdf: Record<string, unknown> | null = null;
    if (iframeVisible && iframeSrc) {
        try {
            const source = iframeSrc.split('#')[0];
            if (source.startsWith('blob:')) {
                const blobResult = await page.evaluate(async (url) => {
                    const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
                    const blob = await response.blob();
                    const base64 = await new Promise<string>((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onerror = () => reject(new Error('Could not read the displayed PDF blob.'));
                        reader.onload = () => resolve(String(reader.result).split(',')[1] ?? '');
                        reader.readAsDataURL(blob);
                    });
                    return { status: response.status, content_type: blob.type, bytes: blob.size, base64 };
                }, source);
                const bytes = decodePdf(blobResult.base64);
                const pdfPath = path.join(attemptDir, 'current.pdf');
                await writeFile(pdfPath, bytes, { mode: 0o600 });
                displayedPdf = {
                    status: blobResult.status,
                    content_type: blobResult.content_type,
                    bytes: bytes.length,
                    sha256: sha256(bytes),
                    artifact: relativePath(caseDir, pdfPath),
                };
            } else if (source.startsWith('data:')) {
                const [metadata, payload] = source.split(',', 2);
                if (!metadata.toLowerCase().startsWith('data:application/pdf;base64')) {
                    throw new Error('The displayed data URL is not an application/pdf base64 payload.');
                }
                const bytes = decodePdf(payload ?? '');
                const pdfPath = path.join(attemptDir, 'current.pdf');
                await writeFile(pdfPath, bytes, { mode: 0o600 });
                displayedPdf = {
                    status: 200,
                    content_type: 'application/pdf',
                    bytes: bytes.length,
                    sha256: sha256(bytes),
                    artifact: relativePath(caseDir, pdfPath),
                };
            } else {
                const frameUrl = new URL(source, baseUrl);
                const frameResponse = capture.responses
                    .filter((response) => response.pathname === frameUrl.pathname && response.resource_type === 'document')
                    .sort((left, right) => left.request_id - right.request_id)
                    .at(-1) ?? null;
                if (frameResponse?.pdf_file) {
                    const framePdf = await readFile(path.join(caseDir, frameResponse.pdf_file));
                    const pdfPath = path.join(attemptDir, 'current.pdf');
                    await writeFile(pdfPath, framePdf, { mode: 0o600 });
                    displayedPdf = {
                        status: frameResponse.status,
                        content_type: frameResponse.content_type,
                        bytes: framePdf.length,
                        sha256: sha256(framePdf),
                        response_sha256: frameResponse.pdf_sha256,
                        artifact: relativePath(caseDir, pdfPath),
                    };
                } else {
                    displayedPdf = {
                        status: frameResponse?.status ?? null,
                        content_type: frameResponse?.content_type ?? null,
                        error: 'The iframe HTTP response body was not captured as a PDF.',
                    };
                }
            }
        } catch (error) {
            displayedPdf = { error: String(error) };
        }
    }

    let screenshotPath: string | null = null;
    let paint: Record<string, unknown> = { status: 'BLOCKED', reason: 'Stage 3 iframe was not available for a native paint observation.' };
    if (iframeVisible) {
        screenshotPath = path.join(attemptDir, 'stage3-painted-iframe.png');
        try {
            await page.waitForFunction(() => new Promise<boolean>((resolve) => {
                const iframeElement = document.querySelector<HTMLIFrameElement>('iframe[title="Template PDF preview"]');
                if (!iframeElement) {
                    resolve(false);
                    return;
                }
                requestAnimationFrame(() => requestAnimationFrame(() => resolve(true)));
            }), undefined, { timeout: 2_000 });
            await page.waitForTimeout(250);

            const screenshot = await iframe.screenshot({ path: screenshotPath, animations: 'disabled', timeout: 15_000 });
            const stats = await page.evaluate(async (base64) => {
                const bytes = Uint8Array.from(atob(base64), (character) => character.charCodeAt(0));
                const bitmap = await createImageBitmap(new Blob([bytes], { type: 'image/png' }));
                const canvas = document.createElement('canvas');
                canvas.width = bitmap.width;
                canvas.height = bitmap.height;
                const context = canvas.getContext('2d', { willReadFrequently: true });
                if (!context) return { width: bitmap.width, height: bitmap.height, dark_pixels: 0, contrast_edges: 0, visible_content: false };
                context.drawImage(bitmap, 0, 0);
                const pixels = context.getImageData(0, 0, bitmap.width, bitmap.height).data;
                const left = Math.floor(bitmap.width * 0.12);
                const right = Math.ceil(bitmap.width * 0.88);
                const top = Math.floor(bitmap.height * 0.2);
                const bottom = Math.ceil(bitmap.height * 0.9);
                let darkPixels = 0;
                let contrastEdges = 0;
                for (let y = top; y < bottom; y += 1) {
                    for (let x = left; x < right - 1; x += 1) {
                        const index = (y * bitmap.width + x) * 4;
                        const nextIndex = index + 4;
                        const red = pixels[index];
                        const green = pixels[index + 1];
                        const blue = pixels[index + 2];
                        const intensity = (red + green + blue) / 3;
                        const nextIntensity = (pixels[nextIndex] + pixels[nextIndex + 1] + pixels[nextIndex + 2]) / 3;
                        if (red < 100 && green < 100 && blue < 100) darkPixels += 1;
                        if (Math.abs(intensity - nextIntensity) > 80) contrastEdges += 1;
                    }
                }
                const regionPixels = Math.max(1, (right - left) * (bottom - top));
                return {
                    width: bitmap.width,
                    height: bitmap.height,
                    dark_pixels: darkPixels,
                    contrast_edges: contrastEdges,
                    dark_ratio: darkPixels / regionPixels,
                    visible_content: darkPixels >= 48 && contrastEdges >= 40,
                };
            }, screenshot.toString('base64'));
            paint = stats.visible_content
                ? { status: 'PASS', method: 'iframe-screenshot-pixel-witness', ...stats }
                : { status: 'BLOCKED', reason: 'The browser screenshot did not positively attest PDF page content; native paint remains unproven.', ...stats };
        } catch (error) {
            paint = { status: 'BLOCKED', reason: `Native PDF iframe screenshot unavailable: ${String(error)}` };
        }
    }

    const saveState = await page.locator('[data-result-sheet-save-state]').first().textContent().catch(() => null);
    const previewError = alertText.map((text) => text.trim()).filter(Boolean).join('\\n') || previewWaitError;
    const responseMatchesDisplayed = Boolean(
        latestPreview
        && displayedPdf
        && typeof displayedPdf.sha256 === 'string'
        && latestPreview.pdf_sha256
        && displayedPdf.sha256 === latestPreview.pdf_sha256,
    );
    const jobPreviewUrl = typeof previewJson?.preview_url === 'string'
        ? new URL(previewJson.preview_url, baseUrl)
        : null;
    const frameUrl = iframeSrc ? new URL(iframeSrc, baseUrl) : null;
    const queuedResponseMatchesFrame = Boolean(
        latestPreview
        && typeof previewJson?.preview_job_id === 'number'
        && jobPreviewUrl?.origin === baseUrl.origin
        && frameUrl?.origin === baseUrl.origin
        && frameUrl.pathname === jobPreviewUrl.pathname,
    );
    const latestPreviewSucceeded = latestPreview !== null && latestPreview.status >= 200 && latestPreview.status < 300;
    const stage3Automatic = latestPreviewSucceeded
        && iframeVisible
        && (responseMatchesDisplayed || queuedResponseMatchesFrame);
    return {
        stage3_navigation_started_at: stage3NavigationStartedAt,
        stage3_entered_at: stage3EnteredAt,
        stage3_preview_request_count_at_entry: stage3RequestCountAtEntry,
        preview_request_count: capture.previewResponses.length,
        no_refresh_button_clicked_by_harness: true,
        refresh_button_present: refreshButtonPresent,
        automatic_preview: stage3Automatic,
        stage: activeStage,
        iframe_visible: iframeVisible,
        iframe_src: iframeSrc,
        iframe_src_kind: iframeSrc?.startsWith('blob:') ? 'blob' : iframeSrc?.startsWith('data:') ? 'data' : iframeSrc ? 'http' : null,
        iframe_pdf: displayedPdf,
        iframe_matches_latest_preview: responseMatchesDisplayed || queuedResponseMatchesFrame,
        preview_request_id: latestPreview?.request_id ?? null,
        preview_http_status: latestPreview?.status ?? null,
        preview_http_content_type: latestPreview?.content_type ?? null,
        preview_request_digest: latestPreview?.request_id
            ? capture.requests.find((request) => request.request_id === latestPreview.request_id)?.preview_input_sha256 ?? null
            : null,
        preview_response_body_file: latestPreview?.body_file ?? null,
        preview_response_pdf_file: latestPreview?.pdf_file ?? null,
        preview_response_pdf_sha256: latestPreview?.pdf_sha256 ?? null,
        preview_response_pdf_bytes: latestPreview?.pdf_bytes ?? null,
        preview_response_summary: previewJson,
        save_disabled: saveDisabled,
        save_state: saveState?.trim() ?? null,
        preview_error: previewError ? previewError.slice(0, 4000) : null,
        stage3_text: stageText.slice(0, 8000),
        paint,
        screenshot: screenshotPath ? relativePath(caseDir, screenshotPath) : null,
    };
}

async function explicitRequesterGet(context: BrowserContext, url: string, targetPath: string): Promise<Record<string, unknown>> {
    const response = await context.request.get(url, {
        headers: { Accept: 'application/json, application/pdf' },
        maxRedirects: 0,
        timeout: 30_000,
    });
    const body = await response.body();
    const contentType = response.headers()['content-type'] ?? '';
    await writeFile(targetPath, body, { mode: 0o600 });
    let bodyJson: Record<string, unknown> | null = null;
    if (contentType.toLowerCase().includes('json')) {
        try {
            const parsed = JSON.parse(body.toString('utf8')) as Record<string, unknown>;
            bodyJson = {
                id: parsed.id ?? null,
                status: parsed.status ?? null,
                ready: parsed.ready ?? null,
                safe_error: parsed.safe_error ?? null,
                progress_done: parsed.progress_done ?? null,
                progress_total: parsed.progress_total ?? null,
            };
        } catch {
            bodyJson = null;
        }
    }
    return {
        url: `${new URL(url).origin}${new URL(url).pathname}`,
        status: response.status(),
        content_type: contentType,
        bytes: body.length,
        sha256: sha256(body),
        body_file: targetPath,
        body_json: bodyJson,
    };
}

async function inspectResultWordRequester(context: BrowserContext, fixtureCase: FixtureCase, attemptDir: string, capture: Capture): Promise<Record<string, unknown>> {
    const preview = [...capture.previewResponses].sort((left, right) => left.request_id - right.request_id).at(-1);
    const previewJson = preview?.parsed_json ?? null;
    const jobId = Number(previewJson?.preview_job_id ?? 0);
    if (!Number.isInteger(jobId) || jobId < 1) {
        return { status: 'BLOCKED', reason: 'No result Word preview_job_id was returned.', preview_json: previewJson };
    }
    if (activeDrains.has(jobId)) {
        await activeDrains.get(jobId);
    }
    const baseUrl = requiredEnvironment('DOCUMENT_PREVIEW_BASE_URL');
    const statusUrl = new URL(`/printing/jobs/${jobId}`, baseUrl).toString();
    const downloadUrl = new URL(`/printing/jobs/${jobId}/download`, baseUrl).toString();
    let status: Record<string, unknown>;
    let download: Record<string, unknown>;
    try {
        status = await explicitRequesterGet(context, statusUrl, path.join(attemptDir, 'requester-status.json'));
        let currentStatusJson = typeof status.body_json === 'object' && status.body_json !== null
            ? status.body_json as Record<string, unknown>
            : null;
        if (currentStatusJson?.status !== 'ready') {
            const deadline = Date.now() + 10_000;
            while (Date.now() < deadline && currentStatusJson?.status !== 'ready' && currentStatusJson?.status !== 'failed') {
                const { promise, resolve } = Promise.withResolvers<void>();
                setTimeout(resolve, 500);
                await promise;
                status = await explicitRequesterGet(context, statusUrl, path.join(attemptDir, 'requester-status.json'));
                currentStatusJson = typeof status.body_json === 'object' && status.body_json !== null
                    ? status.body_json as Record<string, unknown>
                    : null;
            }
        }
    } catch (error) {
        status = { status: 'BLOCKED', reason: String(error) };
    }
    try {
        download = await explicitRequesterGet(context, downloadUrl, path.join(attemptDir, 'requester-download.pdf'));
    } catch (error) {
        download = { status: 'BLOCKED', reason: String(error) };
    }
    const statusJson = typeof status.body_json === 'object' && status.body_json !== null
        ? status.body_json as Record<string, unknown>
        : null;
    const pdfBytes = await readFile(path.join(attemptDir, 'requester-download.pdf')).catch(() => Buffer.alloc(0));
    const pdfSignature = pdfBytes.subarray(0, 5).equals(Buffer.from('%PDF-'));
    const downloadHash = sha256(pdfBytes);
    const currentPdfPath = path.join(attemptDir, 'current.pdf');
    if (pdfSignature) await writeFile(currentPdfPath, pdfBytes, { mode: 0o600 });
    const actualIframeResponse = capture.responses
        .filter((response) => response.pathname === `/printing/jobs/${jobId}/download` && response.resource_type === 'document')
        .sort((left, right) => left.request_id - right.request_id)
        .at(-1) ?? null;
    const snapshotPath = path.join(attemptDir, 'print-job-snapshot.json');
    const snapshot = await readOnlySnapshot(fixtureCase, jobId, snapshotPath).catch((error) => ({ error: String(error) }));
    const job = typeof snapshot.print_job === 'object' && snapshot.print_job !== null
        ? snapshot.print_job as Record<string, unknown>
        : null;
    const expectedChecksum = typeof job?.file_checksum === 'string' ? job.file_checksum : null;
    const actorId = typeof snapshot.actor_id === 'number' ? snapshot.actor_id : null;
    const requesterBound = actorId !== null && job?.requested_by_user_id === actorId;
    const requesterDownloadPass = status.status === 200
        && statusJson?.status === 'ready'
        && String(download.status) === '200'
        && String(download.content_type).toLowerCase().includes('application/pdf')
        && pdfSignature
        && requesterBound
        && expectedChecksum !== null
        && downloadHash === expectedChecksum;
    const iframeHttpPass = actualIframeResponse !== null
        && actualIframeResponse.status === 200
        && actualIframeResponse.content_type.toLowerCase().includes('application/pdf');

    return {
        job_id: jobId,
        preview_response: preview,
        status_requester_http: status,
        status_json: statusJson,
        download_requester_http: download,
        database_snapshot: snapshot,
        actor_id: actorId,
        requester_bound_to_job: requesterBound,
        expected_file_checksum: expectedChecksum,
        downloaded_sha256: downloadHash,
        downloaded_pdf_signature: pdfSignature,
        current_pdf_artifact: pdfSignature ? relativePath(attemptDir, currentPdfPath) : null,
        requester_status_download_checksum_pass: requesterDownloadPass,
        iframe_http_response: actualIframeResponse,
        iframe_http_checksum_pass: iframeHttpPass,
        poll_status_timeline: capture.printStatusTimeline,
        converter_available: fixtureCase.libreoffice_available ?? 'UNVERIFIED',
    };
}

async function saveInactiveTemplate(page: Page, fixtureCase: FixtureCase, capture: Capture, attemptDir: string): Promise<Record<string, unknown>> {
    const activeCheckbox = page.getByLabel('Set as active template');
    const wasActive = await activeCheckbox.isChecked();
    if (wasActive) throw new Error('Refusing to save an active template fixture.');
    const saveButton = page.getByRole('button', { name: /^(Save changes|Save)$/ }).last();
    const disabled = await saveButton.isDisabled();
    if (disabled) throw new Error('The current PDF did not enable Save; no save request was sent.');
    const expectedPath = fixtureCase.surface === 'result'
        ? `/setup/result-templates/${fixtureCase.template.id}`
        : `/setup/report-templates/${fixtureCase.surface}/${fixtureCase.template.id}`;
    const saveResponsePromise = page.waitForResponse((response) => {
        const request = response.request();
        const url = new URL(response.url());
        return url.pathname === expectedPath && ['POST', 'PUT', 'PATCH'].includes(request.method());
    }, { timeout: 30_000 });
    await saveButton.click();
    const response = await saveResponsePromise;
    const contentType = (await response.headerValue('content-type')) ?? '';
    const requestBody = response.request().postDataBuffer();
    let submittedStateSha256: string | null = null;
    if (requestBody) {
        try {
            const payload = JSON.parse(requestBody.toString('utf8')) as Record<string, unknown>;
            const state = payload.state ?? payload;
            submittedStateSha256 = sha256(canonicalJson(state));
        } catch {
            submittedStateSha256 = sha256(requestBody);
        }
    }
    const responseBody = await response.body().catch(() => Buffer.alloc(0));

    let responseErrors: Record<string, unknown> | null = null;
    if (contentType.toLowerCase().includes('json')) {
        try {
            const responseJson = JSON.parse(responseBody.toString('utf8')) as Record<string, unknown>;
            responseErrors = {
                error: responseJson.error ?? null,
                message: responseJson.message ?? null,
                errors: responseJson.errors ?? null,
            };
        } catch {
            responseErrors = { parse_error: 'Save error response JSON was invalid.' };
        }
    }
    const location = await response.headerValue('location');
    const result: Record<string, unknown> = {
        status: response.status(),
        status_text: response.statusText(),
        content_type: contentType,
        location: location ? new URL(location, response.url()).pathname : null,
        response_body_sha256: sha256(responseBody),
        response_body_bytes: responseBody.length,
        response_errors: responseErrors,
        request_method: response.request().method(),
        request_url: `${new URL(response.url()).origin}${new URL(response.url()).pathname}`,
        submitted_state_sha256: submittedStateSha256,
        inactive_before_save: !wasActive,
        save_button_enabled: !disabled,
        successful_response: response.status() >= 200 && response.status() < 400,
        navigated_to_list: false,
    };
    await writeJson(path.join(attemptDir, 'save-response.json'), result);
    if (result.successful_response !== true) return result;

    const listUrl = new URL(fixtureCase.template.list_url, validateOrigin(requiredEnvironment('DOCUMENT_PREVIEW_BASE_URL')));
    try {
        await page.waitForURL((url) => url.pathname === listUrl.pathname, { timeout: 20_000 });
        result.navigated_to_list = true;
    } catch {
        result.navigation_error = 'Successful save response did not navigate to the template list.';
    }
    await capture.drain();
    await writeJson(path.join(attemptDir, 'save-response.json'), result);
    return result;
}


async function attemptJourney(page: Page, context: BrowserContext, fixtureCase: FixtureCase, attempt: number, caseDir: string, capture: Capture, ledger: CaseLedger): Promise<void> {
    const attemptDir = path.join(caseDir, `attempt-${attempt}`);
    await mkdir(attemptDir, { recursive: true, mode: 0o700 });
    const stage = await advanceThroughStages(page, fixtureCase, attempt, caseDir, capture);
    (ledger.state[`attempt_${attempt}`] as Record<string, unknown>) = stage;
    if (typeof stage.screenshot === 'string') {
        ledger.screenshots.push(path.posix.join(ledger.evidence_dir, stage.screenshot));
    }
    ledger.criteria.automatic_stage3_preview = stage.automatic_preview === true && stage.iframe_visible === true
        ? 'PASS'
        : 'FAIL';
    ledger.criteria.painted_iframe = (stage.paint as Record<string, unknown>).status === 'PASS' ? 'PASS' : 'BLOCKED';

    const preview = [...capture.previewResponses].sort((left, right) => left.request_id - right.request_id).at(-1);
    if (fixtureCase.source_kind === 'docx' && fixtureCase.surface === 'result') {
        const previewHttpPass = preview?.status === 200
            && String(preview.content_type).toLowerCase().includes('application/json')
            && preview.parsed_json?.preview_job_id !== null;
        const requester = await inspectResultWordRequester(context, fixtureCase, attemptDir, capture);
        (ledger.state[`attempt_${attempt}_result_word_http`] as Record<string, unknown>) = requester;
        ledger.criteria.preview_transport = previewHttpPass && requester.requester_status_download_checksum_pass === true
            ? 'PASS'
            : 'FAIL';
        ledger.criteria.iframe_http = requester.iframe_http_checksum_pass === true ? 'PASS' : 'FAIL';
        ledger.criteria.blob_display_bytes = 'NOT_APPLICABLE';
        ledger.criteria.pdf_bytes_hash_marker = requester.downloaded_pdf_signature === true
            ? 'PENDING'
            : 'FAIL';
        ledger.criteria.word_converter = fixtureCase.libreoffice_available === true ? 'PASS' : 'BLOCKED';
    } else {
        const responseSummary = preview?.parsed_json ?? null;
        const previewPdfHash = preview?.pdf_sha256 ?? null;
        const iframePdf = stage.iframe_pdf as Record<string, unknown> | null;
        const displayHash = typeof iframePdf?.sha256 === 'string' ? iframePdf.sha256 : null;
        const syncPass = preview?.status === 200
            && String(preview.content_type).toLowerCase().includes('application/json')
            && responseSummary?.has_pdf_base64 === true
            && previewPdfHash !== null;
        ledger.criteria.preview_transport = syncPass ? 'PASS' : 'FAIL';
        ledger.criteria.blob_display_bytes = syncPass
            && iframePdf?.status === 200
            && String(iframePdf.content_type).toLowerCase().includes('application/pdf')
            && displayHash === previewPdfHash
            ? 'PASS'
            : 'FAIL';
        ledger.criteria.iframe_http = 'NOT_APPLICABLE';
        ledger.criteria.pdf_bytes_hash_marker = iframePdf?.artifact && displayHash ? 'PENDING' : 'FAIL';
        ledger.criteria.word_converter = fixtureCase.source_kind === 'docx'
            ? fixtureCase.libreoffice_available === true ? 'PASS' : 'BLOCKED'
            : 'NOT_APPLICABLE';
        ledger.state[`attempt_${attempt}_synchronous_pdf`] = {
            response_status: preview?.status ?? null,
            response_content_type: preview?.content_type ?? null,
            response_json_file: preview?.body_file ?? null,
            response_pdf_file: preview?.pdf_file ?? null,
            response_pdf_sha256: previewPdfHash,
            response_pdf_bytes: preview?.pdf_bytes ?? null,
            decoded_pdf_signature: typeof iframePdf?.sha256 === 'string',
            displayed_blob: iframePdf
                ? { status: iframePdf.status, content_type: iframePdf.content_type, bytes: iframePdf.bytes, sha256: displayHash }
                : null,
            displayed_blob_sha256_matches_response: Boolean(displayHash && displayHash === previewPdfHash),
            artifact_pdf: iframePdf?.artifact ?? null,
        };
    }

    if (stage.iframe_visible !== true || stage.save_disabled !== false) {
        throw new Error(`Stage 3 did not expose a current PDF and enabled Save (error: ${String(stage.preview_error ?? 'none')}).`);
    }
    const saveResult = await saveInactiveTemplate(page, fixtureCase, capture, attemptDir);
    (ledger.state[`attempt_${attempt}_save`] as Record<string, unknown>) = saveResult;
    ledger.criteria.inactive_save_reopen_repeat = saveResult.successful_response === true
        && saveResult.navigated_to_list === true
        && saveResult.inactive_before_save === true
        ? 'PENDING'
        : 'FAIL';
}

async function captureAttemptResult(
    page: Page,
    context: BrowserContext,
    fixtureCase: FixtureCase,
    attempt: number,
    caseDir: string,
    capture: Capture,
    ledger: CaseLedger,
    checkpoint: (ledger: CaseLedger) => Promise<void>,
): Promise<void> {
    const initialCriteria: CriterionLedger = {
        index_png: ledger.criteria.index_png,
        automatic_stage3_preview: 'BLOCKED',
        preview_transport: 'BLOCKED',
        pdf_bytes_hash_marker: 'BLOCKED',
        iframe_http: fixtureCase.id === 'result-word' ? 'BLOCKED' : 'NOT_APPLICABLE',
        blob_display_bytes: fixtureCase.id === 'result-word' ? 'NOT_APPLICABLE' : 'BLOCKED',
        painted_iframe: 'BLOCKED',
        inactive_save_reopen_repeat: 'BLOCKED',
        word_converter: fixtureCase.source_kind === 'docx' ? 'BLOCKED' : 'NOT_APPLICABLE',
    };
    const attemptResult: AttemptLedger = {
        attempt,
        outcome: 'BLOCKED',
        criteria: { ...initialCriteria },
        state: {},
        errors: [],
    };
    ledger.attempt_results[attempt - 1] = attemptResult;
    Object.assign(ledger.criteria, initialCriteria);
    ledger.state[`attempt_${attempt}`] = {};
    try {
        await attemptJourney(page, context, fixtureCase, attempt, caseDir, capture, ledger);
    } catch (error) {
        const message = redact(error instanceof Error ? error.stack ?? error.message : String(error), fixtureCase).slice(0, 8000);
        attemptResult.errors.push(message);
        ledger.errors.push(message);
    }
    attemptResult.criteria = { ...ledger.criteria };
    attemptResult.state = {
        ...(ledger.state[`attempt_${attempt}`] as Record<string, unknown> | undefined),
        ...(ledger.state[`attempt_${attempt}_result_word_http`] as Record<string, unknown> | undefined),
    };
    attemptResult.outcome = outcomeFor(attemptResult.criteria, attemptResult.errors);
    if (attempt > 1) {
        for (const [key, currentStatus] of Object.entries(attemptResult.criteria)) {
            const previousStatus = ledger.attempt_results[0]?.criteria[key];
            if (previousStatus === undefined) continue;
            ledger.criteria[key] = currentStatus === 'FAIL' || previousStatus === 'FAIL'
                ? 'FAIL'
                : currentStatus === 'BLOCKED' || previousStatus === 'BLOCKED'
                    ? 'BLOCKED'
                    : currentStatus === 'PENDING' || previousStatus === 'PENDING'
                        ? 'PENDING'
                        : currentStatus === 'PASS' || previousStatus === 'PASS'
                            ? 'PASS'
                            : 'NOT_APPLICABLE';
        }
    }
    await checkpoint(ledger);
}

async function captureCase(browser: Browser, fixtureCase: FixtureCase, baseUrl: URL, artifactRoot: string, runId: string, startedAt: string, checkpoint: (ledger: CaseLedger) => Promise<void>): Promise<CaseLedger> {
    const caseDir = path.join(artifactRoot, fixtureCase.id);
    await mkdir(caseDir, { recursive: true, mode: 0o700 });
    const context = await browser.newContext({ serviceWorkers: 'block', acceptDownloads: false });
    const page = await context.newPage();
    const capture = createCapture(page, fixtureCase, caseDir, baseUrl);
    const criteria: CriterionLedger = {
        index_png: 'BLOCKED',
        automatic_stage3_preview: 'BLOCKED',
        preview_transport: 'BLOCKED',
        pdf_bytes_hash_marker: 'BLOCKED',
        iframe_http: fixtureCase.id === 'result-word' ? 'BLOCKED' : 'NOT_APPLICABLE',
        blob_display_bytes: fixtureCase.id === 'result-word' ? 'NOT_APPLICABLE' : 'BLOCKED',
        painted_iframe: 'BLOCKED',
        inactive_save_reopen_repeat: 'BLOCKED',
        word_converter: fixtureCase.source_kind === 'docx' ? 'BLOCKED' : 'NOT_APPLICABLE',
    };
    const browserEvidence = {
        browser_name: browser.browserType().name(),
        browser_version: browser.version(),
        channel: 'chromium',
        native_pdf_paint_screenshot: true,
        paint_settle: { animation_frames: 2, delay_ms: 250, timeout_ms: 2000 },
    };

    const ledger: CaseLedger = {
        run_id: runId,
        started_at: startedAt,
        id: fixtureCase.id,
        surface: fixtureCase.surface,
        source_kind: fixtureCase.source_kind,
        template_id: fixtureCase.template.id,
        actor_id: fixtureCase.actor.id ?? null,
        marker: fixtureCase.marker,
        outcome: 'BLOCKED',
        criteria,
        attempt_results: [],
        evidence_dir: relativePath(artifactRoot, caseDir),
        requests: capture.requests,
        responses: capture.responses,
        draft_responses: capture.draftResponses,
        print_status_timeline: capture.printStatusTimeline,
        console_errors: capture.consoleErrors,
        page_errors: capture.pageErrors,
        request_failures: capture.requestFailures,
        blocked_foreign_requests: capture.blockedForeignRequests,
        served_assets: [],
        screenshots: [],
        snapshot_files: [],
        errors: [],
        state: {
            actor_id: fixtureCase.actor.id,
            converter_available: fixtureCase.libreoffice_available,
            browser: browserEvidence,
        },
    };
    ledger.attempt_results = [1, 2].map((attempt) => ({
        attempt,
        outcome: 'BLOCKED',
        criteria: { ...criteria },
        state: {},
        errors: [],
    }));
    await writeJson(path.join(caseDir, 'case.json'), ledger);
    await checkpoint(ledger);
    const caseDeadline = setTimeout(() => {
        ledger.errors.push(`Per-case deadline exceeded after ${caseTimeoutMs}ms.`);
        void context.close();
    }, caseTimeoutMs);
    await context.route('**/*', async (route) => {
        const requestUrl = new URL(route.request().url());
        if (['data:', 'blob:', 'chrome-extension:', 'about:'].includes(requestUrl.protocol)
            || requestUrl.protocol === 'chrome:'
            || requestUrl.origin === 'chrome://resources'
            || requestUrl.origin === baseUrl.origin) {
            await route.continue();
            return;
        }
        capture.blockedForeignRequests.push(`${route.request().method()} ${requestUrl.origin}${requestUrl.pathname}`);
        await route.abort('blockedbyclient');
    });

    try {
        await page.goto(new URL('/login', baseUrl).toString(), { waitUntil: 'domcontentloaded' });
        await page.locator('#email').fill(fixtureCase.actor.email);
        await page.locator('#password').fill(fixtureCase.actor.password);
        await page.locator('button[type="submit"]').click();
        await page.waitForURL((url) => url.pathname !== '/login', { timeout: 20_000 });

        await page.goto(new URL(fixtureCase.template.list_url, baseUrl).toString(), { waitUntil: 'domcontentloaded' });
        const firstPngPath = path.join(caseDir, 'index-thumbnail.png');
        const firstPng = await readIndexPng(page, fixtureCase, firstPngPath);
        ledger.state.index_png = firstPng;
        ledger.criteria.index_png = firstPng.status === 200
            && String(firstPng.content_type).toLowerCase().includes('image/png')
            && firstPng.png_signature === true
            && Number(firstPng.natural_width) > 0
            ? 'PASS'
            : 'FAIL';
        await capture.drain();

        const beforePath = path.join(caseDir, 'template-before.json');
        const before = await readOnlySnapshot(fixtureCase, null, beforePath);
        ledger.snapshot_files.push(relativePath(artifactRoot, beforePath));
        const beforeTemplate = snapshotTemplate(before);
        const beforeSourceHash = sourceHashFromSnapshot(before, fixtureCase);
        const beforeBuilderSha = typeof beforeTemplate?.builder_json_sha256 === 'string' ? beforeTemplate.builder_json_sha256 : null;
        const builderShaMatches = fixtureCase.source_kind !== 'builder' || beforeBuilderSha === fixtureCase.builder_sha256;
        const documentChecksumMatches = fixtureCase.source_kind !== 'docx' || beforeTemplate?.document_checksum === beforeSourceHash;
        const isInactive = beforeTemplate?.is_active === false;
        const sourceHashMatches = beforeSourceHash === fixtureCase.source_sha256;
        const sourceMatchesManifest = sourceHashMatches && builderShaMatches && documentChecksumMatches;
        ledger.state.source_before = {
            source_sha256: beforeSourceHash,
            expected_source_sha256: fixtureCase.source_sha256,
            document_checksum: beforeTemplate?.document_checksum ?? null,
            document_checksum_matches_bytes: documentChecksumMatches,
            builder_sha256: beforeBuilderSha,
            expected_builder_sha256: fixtureCase.builder_sha256 ?? null,
            builder_sha256_matches: builderShaMatches,
            template_checksum: templateChecksumFromSnapshot(before),
            source_kind: beforeTemplate?.source_kind ?? null,
            builder_schema_version: beforeTemplate?.builder_schema_version ?? null,
            compiler_version: beforeTemplate?.compiler_version ?? null,
            is_active: beforeTemplate?.is_active ?? null,
            matches_manifest: sourceMatchesManifest,
        };
        if (!isInactive || !sourceMatchesManifest || beforeTemplate?.source_kind !== fixtureCase.source_kind) {
            throw new Error(`Source/active fixture snapshot disagreed with the isolated manifest (inactive=${isInactive}, source_match=${sourceMatchesManifest}).`);
        }

        try {
            await openExistingEdit(page, fixtureCase, capture);
        } catch (error) {
            ledger.errors.push(`Attempt 1 could not open the editor: ${redact(String(error), fixtureCase)}`);
        }
        await captureAttemptResult(page, context, fixtureCase, 1, caseDir, capture, ledger, checkpoint);

        try {
            await page.goto(new URL(fixtureCase.template.list_url, baseUrl).toString(), { waitUntil: 'domcontentloaded' });
            const rowAfterSave = await findTemplateRow(page, fixtureCase);
            await expect(rowAfterSave.getByText('Inactive', { exact: true })).toBeVisible({ timeout: 10_000 });
            const reopenPngPath = path.join(caseDir, 'index-thumbnail-reopen.png');
            const reopenPng = await readIndexPng(page, fixtureCase, reopenPngPath);
            ledger.state.index_png_after_save = reopenPng;
            if (reopenPng.status !== 200 || !String(reopenPng.content_type).toLowerCase().includes('image/png') || !reopenPng.png_signature || Number(reopenPng.natural_width) < 1) {
                ledger.criteria.index_png = 'FAIL';
            }
            const expectedEditPath = fixtureCase.surface === 'result'
                ? `/setup/result-templates/${fixtureCase.template.id}/edit`
                : `/setup/report-templates/${fixtureCase.surface}/${fixtureCase.template.id}/edit`;
            const reopenRow = await findTemplateRow(page, fixtureCase);
            await reopenRow.getByRole('link', { name: /edit/i }).or(reopenRow.getByRole('button', { name: /edit/i })).first().click();
            await page.waitForURL((url) => url.pathname === expectedEditPath, { timeout: 20_000 });
            await expect(page.locator('[data-result-sheet-workflow]')).toBeVisible();
            const reopened = await readOnlySnapshot(fixtureCase, null, path.join(caseDir, 'template-after-attempt-1.json'));
            const saveResult = ledger.state.attempt_1_save as Record<string, unknown> | undefined;
            const submittedHash = typeof saveResult?.submitted_state_sha256 === 'string' ? saveResult.submitted_state_sha256 : null;
            const reopenedDraft = reopened.draft as Record<string, unknown> | null;
            const templateAfterSave = snapshotTemplate(reopened);
            const persistedHash = fixtureCase.source_kind === 'builder'
                ? typeof templateAfterSave?.builder_json_sha256 === 'string' ? templateAfterSave.builder_json_sha256 : null
                : typeof templateAfterSave?.source_bytes_sha256 === 'string' ? templateAfterSave.source_bytes_sha256 : null;
            const savedStateMatchesFixture = fixtureCase.source_kind === 'builder'
                ? persistedHash === fixtureCase.builder_sha256
                : persistedHash === fixtureCase.source_sha256;
            const draftRetired = reopenedDraft === null;
            const saveProof = {
                saved_state_sha256: persistedHash,
                expected_source_sha256: fixtureCase.source_sha256,
                expected_builder_sha256: fixtureCase.builder_sha256 ?? null,
                submitted_state_sha256: submittedHash,
                saved_state_matches_fixture: savedStateMatchesFixture,
                draft_retired: draftRetired,
                draft_snapshot: reopenedDraft,
            };
            ledger.state.attempt_1_persistence = saveProof;
            const persistencePass = savedStateMatchesFixture && draftRetired
                && saveResult?.successful_response === true && saveResult.navigated_to_list === true;
            ledger.attempt_results[0].criteria.inactive_save_reopen_repeat = persistencePass ? 'PASS' : 'FAIL';
            if (!persistencePass) ledger.criteria.inactive_save_reopen_repeat = 'FAIL';
        } catch (error) {
            ledger.errors.push(`Attempt 2 could not reopen and prove saved state: ${redact(String(error), fixtureCase)}`);
            ledger.criteria.inactive_save_reopen_repeat = 'FAIL';
        }
        await captureAttemptResult(page, context, fixtureCase, 2, caseDir, capture, ledger, checkpoint);

        await page.goto(new URL(fixtureCase.template.list_url, baseUrl).toString(), { waitUntil: 'domcontentloaded' });
        const finalRow = await findTemplateRow(page, fixtureCase);
        await expect(finalRow.getByText('Inactive', { exact: true })).toBeVisible({ timeout: 10_000 });
        const afterPath = path.join(caseDir, 'template-after.json');
        const after = await readOnlySnapshot(fixtureCase, null, afterPath);
        ledger.snapshot_files.push(relativePath(artifactRoot, afterPath));
        const afterTemplate = snapshotTemplate(after);
        const afterSourceHash = sourceHashFromSnapshot(after, fixtureCase);
        const afterBuilderSha = typeof afterTemplate?.builder_json_sha256 === 'string' ? afterTemplate.builder_json_sha256 : null;
        const afterBuilderShaMatches = fixtureCase.source_kind !== 'builder' || afterBuilderSha === fixtureCase.builder_sha256;
        const afterDocumentChecksumMatches = fixtureCase.source_kind !== 'docx' || afterTemplate?.document_checksum === afterSourceHash;
        const afterSourceMatchesManifest = afterSourceHash === fixtureCase.source_sha256
            && afterBuilderShaMatches
            && afterDocumentChecksumMatches;
        ledger.state.source_after = {
            source_sha256: afterSourceHash,
            expected_source_sha256: fixtureCase.source_sha256,
            document_checksum: afterTemplate?.document_checksum ?? null,
            document_checksum_matches_bytes: afterDocumentChecksumMatches,
            builder_sha256: afterBuilderSha,
            expected_builder_sha256: fixtureCase.builder_sha256 ?? null,
            builder_sha256_matches: afterBuilderShaMatches,
            template_checksum: templateChecksumFromSnapshot(after),
            builder_schema_version: afterTemplate?.builder_schema_version ?? null,
            compiler_version: afterTemplate?.compiler_version ?? null,
            is_active: afterTemplate?.is_active ?? null,
            matches_manifest: afterSourceMatchesManifest,
        };
        const finalDraft = after.draft as Record<string, unknown> | null;
        const attemptTwoSave = ledger.state.attempt_2_save as Record<string, unknown> | undefined;
        const attemptTwoPersisted = afterSourceMatchesManifest && finalDraft === null
            && attemptTwoSave?.successful_response === true
            && attemptTwoSave.navigated_to_list === true;
        ledger.state.attempt_2_persistence = {
            saved_state_sha256: afterSourceHash,
            expected_source_sha256: fixtureCase.source_sha256,
            expected_builder_sha256: fixtureCase.builder_sha256 ?? null,
            draft_retired: finalDraft === null,
            saved_state_matches_fixture: afterSourceMatchesManifest,
        };
        ledger.attempt_results[1].criteria.inactive_save_reopen_repeat = attemptTwoPersisted ? 'PASS' : 'FAIL';
        ledger.criteria.inactive_save_reopen_repeat = ledger.attempt_results[0].criteria.inactive_save_reopen_repeat === 'PASS'
            && ledger.attempt_results[1].criteria.inactive_save_reopen_repeat === 'PASS'
            ? 'PASS'
            : 'FAIL';
        if (!afterSourceMatchesManifest || afterTemplate?.is_active !== false) {
            throw new Error('The saved/reopened source or inactive state changed from the isolated fixture manifest.');
        }
    } catch (error) {
        ledger.errors.push(redact(error instanceof Error ? error.stack ?? error.message : String(error), fixtureCase).slice(0, 8000));
    } finally {
        const failureSnapshot = await page.evaluate(() => {
            const body = document.querySelector('body');
            const iframe = document.querySelector<HTMLIFrameElement>('iframe[title="Template PDF preview"]');
            const stage = document.querySelector<HTMLElement>('[data-result-sheet-workflow-stage][aria-current="step"]');
            const alerts = [...document.querySelectorAll('[data-result-sheet-stage="verify"] [role="alert"]')];
            const saveButtons = [...document.querySelectorAll<HTMLButtonElement>('button')].filter((button) => /^(Save changes|Save)$/.test(button.innerText.trim()));
            return body ? {
                url: location.href,
                current_stage: stage?.getAttribute('data-result-sheet-workflow-stage') ?? null,
                iframe_src: iframe?.getAttribute('src') ?? null,
                preview_error: alerts.map((alert) => alert.textContent?.trim() ?? ''),
                save_disabled: saveButtons.at(-1)?.disabled ?? null,
                body_text: (body.innerText ?? '').slice(0, 12_000),
            } : null;
        }).catch(() => null);
        await writeJson(path.join(caseDir, 'failure-state.json'), failureSnapshot);
        ledger.state.failure_snapshot_available = failureSnapshot !== null;
        ledger.served_assets = [...capture.servedAssets.values()];
        ledger.state.asset_changes = capture.assetChanges;
        ledger.responses = capture.responses;
        ledger.draft_responses = capture.draftResponses;
        ledger.requests = capture.requests;
        ledger.console_errors = capture.consoleErrors;
        ledger.page_errors = capture.pageErrors;
        ledger.request_failures = capture.requestFailures;
        ledger.blocked_foreign_requests = capture.blockedForeignRequests;
        ledger.outcome = outcomeFor(ledger.criteria, ledger.errors);
        await writeJson(path.join(caseDir, 'case.json'), ledger);
        const finalScreenshot = path.join(caseDir, 'case-final.png');
        try {
            await page.screenshot({ path: finalScreenshot, fullPage: true, animations: 'disabled', timeout: 15_000 });
            ledger.screenshots.push(relativePath(artifactRoot, finalScreenshot));
        } catch (error) {
            ledger.errors.push(`Final screenshot unavailable: ${redact(String(error), fixtureCase)}`);
        }
        await capture.drain();
        clearTimeout(caseDeadline);
        ledger.outcome = outcomeFor(ledger.criteria, ledger.errors);
        await context.close().catch(() => undefined);
        await writeJson(path.join(caseDir, 'case.json'), ledger);
        await checkpoint(ledger);
    }

    return ledger;
}

function pendingCaseLedger(fixtureCase: FixtureCase, artifactRoot: string, runId: string, startedAt: string): CaseLedger {
    const criteria: CriterionLedger = {
        index_png: 'BLOCKED',
        automatic_stage3_preview: 'BLOCKED',
        preview_transport: 'BLOCKED',
        pdf_bytes_hash_marker: 'BLOCKED',
        iframe_http: fixtureCase.id === 'result-word' ? 'BLOCKED' : 'NOT_APPLICABLE',
        blob_display_bytes: fixtureCase.id === 'result-word' ? 'NOT_APPLICABLE' : 'BLOCKED',
        painted_iframe: 'BLOCKED',
        inactive_save_reopen_repeat: 'BLOCKED',
        word_converter: fixtureCase.source_kind === 'docx' ? 'BLOCKED' : 'NOT_APPLICABLE',
    };
    return {
        run_id: runId,
        started_at: startedAt,
        id: fixtureCase.id,
        surface: fixtureCase.surface,
        source_kind: fixtureCase.source_kind,
        template_id: fixtureCase.template.id,
        actor_id: fixtureCase.actor.id ?? null,
        marker: fixtureCase.marker,
        outcome: 'BLOCKED',
        criteria,
        attempt_results: [1, 2].map((attempt) => ({ attempt, outcome: 'BLOCKED', criteria: { ...criteria }, state: {}, errors: [] })),
        evidence_dir: relativePath(artifactRoot, path.join(artifactRoot, fixtureCase.id)),
        requests: [],
        responses: [],
        draft_responses: [],
        print_status_timeline: [],
        console_errors: [],
        page_errors: [],
        request_failures: [],
        blocked_foreign_requests: [],
        served_assets: [],
        screenshots: [],
        snapshot_files: [],
        errors: [],
        state: {},
    };
}

test('document preview transport capture — diagnostic evidence only', async ({ browser }) => {
    test.setTimeout(20 * 60 * 1000);
    const runId = requiredEnvironment('DOCUMENT_PREVIEW_RUN_ID');
    const baseUrl = validateOrigin(requiredEnvironment('DOCUMENT_PREVIEW_BASE_URL'));
    const artifactRoot = path.resolve(requiredEnvironment('DOCUMENT_PREVIEW_ARTIFACTS'));
    const manifestPath = path.resolve(requiredEnvironment('DOCUMENT_PREVIEW_MANIFEST'));
    const manifest = JSON.parse(await readFile(manifestPath, 'utf8')) as FixtureManifest;
    const fixtureCases = validateManifest(manifest, runId);
    if (baseUrl.port !== String(manifest.public_port)) {
        throw new Error('The relay URL port does not match the port in the exact served fixture manifest.');
    }
    const initialLedger = JSON.parse(await readFile(path.join(artifactRoot, 'ledger.json'), 'utf8')) as { run_id?: unknown; started_at?: unknown };
    if (initialLedger.run_id !== runId || typeof initialLedger.started_at !== 'string' || initialLedger.started_at.length === 0) {
        throw new Error('The driver-created run ledger is missing the exact run identity or fixed start time.');
    }
    const startedAt = initialLedger.started_at;
    const ledgers = fixtureCases.map((fixtureCase) => pendingCaseLedger(fixtureCase, artifactRoot, runId, startedAt));
    const assetHashes = new Map<string, string>();
    const assetChanges: Array<Record<string, unknown>> = [];
    const writeCheckpoint = async (): Promise<void> => {
        const nonPassing = ledgers.filter((ledger) => ledger.errors.length > 0 || ledger.outcome !== 'PASS' || Object.values(ledger.criteria).some((status) => status !== 'PASS' && status !== 'NOT_APPLICABLE'));
        await writeJson(path.join(artifactRoot, 'ledger.json'), {
            schema_version: 1,
            run_id: runId,
            diagnostic_only: true,
            feature_acceptance: false,
            started_at: startedAt,
            outcome: ledgers.some((ledger) => ledger.outcome === 'FAIL' || Object.values(ledger.criteria).includes('FAIL')) ? 'FAIL' : canDeferPdfRaster(ledgers, assetChanges) ? 'PENDING' : nonPassing.length > 0 || assetChanges.length > 0 ? 'BLOCKED' : 'PASS',
            cases: ledgers,
            served_asset_manifest: [...assetHashes].map(([url, hash]) => ({ url, sha256: hash })),
            asset_changes: assetChanges,
        });
    };
    await writeCheckpoint();
    for (const fixtureCase of fixtureCases) {
        const caseIndex = ledgers.findIndex((ledger) => ledger.id === fixtureCase.id);
        const checkpointCase = async (ledger: CaseLedger): Promise<void> => {
            ledgers[caseIndex] = ledger;
            await writeCheckpoint();
        };
        try {
            const ledger = await captureCase(browser, fixtureCase, baseUrl, artifactRoot, runId, startedAt, checkpointCase);
            ledgers[caseIndex] = ledger;
            for (const asset of ledger.served_assets) {
                const url = String(asset.url);
                const hash = String(asset.sha256);
                const previous = assetHashes.get(url);
                if (previous && previous !== hash) assetChanges.push({ url, before: previous, after: hash, case_id: fixtureCase.id });
                assetHashes.set(url, hash);
            }
            for (const change of (ledger.state.asset_changes as Array<Record<string, unknown>> | undefined) ?? []) assetChanges.push(change);
        } catch (error) {
            const failed = ledgers[caseIndex];
            failed.errors.push(redact(error instanceof Error ? error.stack ?? error.message : String(error), fixtureCase).slice(0, 8000));
            failed.outcome = 'BLOCKED';
            await writeJson(path.join(artifactRoot, fixtureCase.id, 'case.json'), failed);
        }
        await writeCheckpoint();
    }

    const nonPassing = ledgers.filter((ledger) => ledger.errors.length > 0 || ledger.outcome !== 'PASS' || Object.values(ledger.criteria).some((status) => status !== 'PASS' && status !== 'NOT_APPLICABLE'));
    await writeCheckpoint();
    const deferredRasterOnly = canDeferPdfRaster(ledgers, assetChanges);
    if ((nonPassing.length > 0 && !deferredRasterOnly) || assetChanges.length > 0) {
        throw new Error(`Diagnostic capture recorded non-passing case criteria; continue from ledger.json: ${nonPassing.map((ledger) => `${ledger.id}=${ledger.outcome}`).join(', ')}`);
    }
});
