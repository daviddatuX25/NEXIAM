import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { expect, test, type Browser, type BrowserContext, type Download, type Page, type Response } from '@playwright/test';

const DOWNLOAD_TIMEOUT_MS = 15_000;

test.use({ channel: 'chromium' });

type SourceKind = 'docx' | 'builder';
type Surface = 'passing_report' | 'course_quota_qualifiers';

type OperationalReportCase = {
    id: string;
    surface: Surface;
    source_kind: SourceKind;
    marker: string;
    source_sha256: string;
    actor: { id: number; email: string; password: string };
    template: { id: number; name: string; is_active: boolean; list_url: string };
    institution_id: number;
    academic_cycle_id: number;
    program_code: string;
    filters: { format: string; academic_cycle_id: number; program_code: string; cutoff_score?: number };
    expected: {
        total_examinees?: number;
        total_passed?: number;
        passing_rate?: string;
        target_quota?: number;
        accepted_count?: number;
        committed_reservations?: number;
        remaining_slots?: number;
        fill_rate?: string;
        passers?: Array<{ reference_code: string; name: string; score: number }>;
        qualifiers?: Array<{ reference_code: string; name: string; gwa: string; accepted_at: string }>;
        excluded_references?: string[];
    };
};
type CaseOutcome = 'PASS' | 'FAIL' | 'ATTEMPTED' | 'NOT_ATTEMPTED';

type CaseLedgerEntry = {
    id: string;
    surface: Surface;
    source_kind: SourceKind;
    template_id: number;
    marker: string;
    outcome: CaseOutcome;
    error?: string;
    hub_downloads?: Record<string, unknown>;
    filtered_exports?: Record<string, unknown>;
    wrong_office_denial?: {
        actor_email: string;
        status: number;
        rendered_pdf_blocked: boolean;
    };
    foreign_cycle_filter?: {
        cycle_id: string | null;
        status: number;
        filter_accepted: boolean;
    };
};

type OperationalManifest = {
    schema_version: 2;
    run_id: string;
    service: 'document-recovery';
    public_port: number;
    source_digest: string;
    build_digest: string;
    operational_reports?: {
        run_id: string;
        source_kind: SourceKind;
        cases: OperationalReportCase[];
        baseline_side_effect_counts?: Record<string, number>;
    };
};

function extractPdfText(pdfPath: string): string {
    const result = spawnSync('pdftotext', ['-layout', pdfPath, '-'], { encoding: 'utf8' });
    if (result.status === 0) {
        return result.stdout;
    }
    throw new Error(`pdftotext failed for ${pdfPath} (${result.status}): ${result.stderr}`);
}

function extractDocxXml(docxPath: string): string {
    const result = spawnSync('unzip', ['-p', docxPath, 'word/document.xml'], { encoding: 'utf8' });
    if (result.status === 0) {
        return result.stdout;
    }
    throw new Error(`unzip failed for ${docxPath} (${result.status}): ${result.stderr}`);
}

test.describe.serial('Operational Report PDF, Word, and CSV Consumer Suite', () => {
    test('captures real button downloads, filtered exports, opposite-actor denials, and audit evidence', async ({ browser }: { browser: Browser }) => {
        test.setTimeout(180_000);
        const runId = process.env.DOCUMENT_PREVIEW_RUN_ID;
        const baseUrl = process.env.DOCUMENT_PREVIEW_BASE_URL;
        const artifactRoot = process.env.DOCUMENT_PREVIEW_ARTIFACTS;
        const manifestPath = process.env.DOCUMENT_PREVIEW_MANIFEST;
        const reportSource = process.env.DOCUMENT_OPERATIONAL_REPORT_SOURCE as SourceKind | undefined;

        expect(runId, 'DOCUMENT_PREVIEW_RUN_ID must be set').toBeTruthy();
        expect(baseUrl, 'DOCUMENT_PREVIEW_BASE_URL must be set').toBeTruthy();
        expect(artifactRoot, 'DOCUMENT_PREVIEW_ARTIFACTS must be set').toBeTruthy();
        expect(manifestPath, 'DOCUMENT_PREVIEW_MANIFEST must be set').toBeTruthy();
        expect(reportSource, 'DOCUMENT_OPERATIONAL_REPORT_SOURCE must be docx or builder').toMatch(/^(docx|builder)$/);

        const parsedBaseUrl = new URL(baseUrl!);
        expect(parsedBaseUrl.origin).toBe(`http://127.0.0.1:${parsedBaseUrl.port}`);

        const manifestRaw = await readFile(manifestPath!, 'utf8');
        const manifest: OperationalManifest = JSON.parse(manifestRaw);

        expect(manifest.operational_reports, 'Manifest must contain operational_reports block').toBeDefined();
        const opReports = manifest.operational_reports!;
        expect(opReports.run_id).toBe(runId);
        expect(opReports.source_kind).toBe(reportSource);
        expect(opReports.cases.length).toBe(2);

        const passingCase = opReports.cases.find((item) => item.id === 'passing-report-operational');
        const quotaCase = opReports.cases.find((item) => item.id === 'course-quota-operational');

        expect(passingCase, 'passing-report-operational case must exist').toBeDefined();
        expect(quotaCase, 'course-quota-operational case must exist').toBeDefined();

        const startedAt = new Date().toISOString();
        const caseEntries: CaseLedgerEntry[] = [
            {
                id: passingCase.id,
                surface: passingCase.surface,
                source_kind: passingCase.source_kind,
                template_id: passingCase.template.id,
                marker: passingCase.marker,
                outcome: 'NOT_ATTEMPTED',
            },
            {
                id: quotaCase.id,
                surface: quotaCase.surface,
                source_kind: quotaCase.source_kind,
                template_id: quotaCase.template.id,
                marker: quotaCase.marker,
                outcome: 'NOT_ATTEMPTED',
            },
        ];

        const persistLedger = async () => {
            const isAllPass = caseEntries.length === 2 && caseEntries.every((c) => c.outcome === 'PASS');
            const operationalLedger = {
                schema_version: 1,
                run_id: runId,
                started_at: startedAt,
                completed_at: new Date().toISOString(),
                source_kind: reportSource,
                outcome: isAllPass ? 'PASS' : 'FAIL',
                cases: caseEntries,
            };

            await writeFile(
                path.join(artifactRoot!, 'operational-report-ledger.json'),
                `${JSON.stringify(operationalLedger, null, 2)}\n`,
                { mode: 0o600 }
            );
        };

        await persistLedger();

        const caseErrors: Error[] = [];
        const orderedCases = [passingCase!, quotaCase!];

        try {
            for (let i = 0; i < orderedCases.length; i++) {
                const fixtureCase = orderedCases[i];
                const caseEntry = caseEntries[i];
                caseEntry.outcome = 'ATTEMPTED';
                await persistLedger();

                const isPassing = fixtureCase.id === 'passing-report-operational';
                const oppositeActor = isPassing ? quotaCase!.actor : passingCase!.actor;
                const reportType = isPassing ? 'passing-report' : 'course-quota-qualifiers';
                const cardTitle = isPassing ? 'Passing Candidates Census' : 'Course Quota Qualifiers';
                const domainTabRegex = isPassing ? /Guidance Testing Reports/i : /Registrar Reports/i;

                const caseDir = path.join(artifactRoot!, fixtureCase.id);
                await mkdir(caseDir, { recursive: true, mode: 0o700 });

                let context: BrowserContext | null = null;
                try {
                    context = await browser.newContext({
                        serviceWorkers: 'block',
                        acceptDownloads: true,
                    });

                    await context.route('**/*', async (route) => {
                        const requestUrl = new URL(route.request().url());
                        if (['data:', 'blob:', 'chrome-extension:', 'about:'].includes(requestUrl.protocol)
                            || requestUrl.origin === parsedBaseUrl.origin) {
                            await route.continue();
                            return;
                        }
                        await route.abort('blockedbyclient');
                    });

                    const page: Page = await context.newPage();

                    // 1. Authenticated Staff Login
                    await page.goto(new URL('/login', baseUrl!).toString(), { waitUntil: 'domcontentloaded' });
                    await page.locator('#email').fill(fixtureCase.actor.email);
                    await page.locator('#password').fill(fixtureCase.actor.password);
                    await page.locator('button[type="submit"]').click();
                    await page.waitForURL((url) => url.pathname !== '/login', { timeout: 20_000 });

                    // 2. Navigate to Reports Index Hub
                    await page.goto(new URL('/reports', baseUrl!).toString(), { waitUntil: 'domcontentloaded' });
                    await expect(page.locator('#main-content h1, h1')).toContainText('Reports & Analytics Hub');

                    // 3. Confirm and select Cycle filter
                    const cycleSelect = page.locator('select').first();
                    await expect(cycleSelect).toBeVisible();

                    const currentSelectedCycle = await cycleSelect.inputValue();
                    if (currentSelectedCycle !== String(fixtureCase.academic_cycle_id)) {
                        await cycleSelect.selectOption(String(fixtureCase.academic_cycle_id));
                        await page.waitForLoadState('domcontentloaded');
                    }

                    // Discover foreign cycle option from the dropdown
                    const cycleOptions = await cycleSelect.locator('option').all();
                    let foreignCycleId: string | null = null;
                    for (const option of cycleOptions) {
                        const val = await option.getAttribute('value');
                        if (val && val !== String(fixtureCase.academic_cycle_id)) {
                            foreignCycleId = val;
                            break;
                        }
                    }

                    // 4. Switch Domain Tab if visible
                    const domainTab = page.getByRole('button', { name: domainTabRegex });
                    if (await domainTab.isVisible()) {
                        await domainTab.click();
                    }

                    // 5. Find Report Card
                    const card = page.locator('div.grid > div').filter({
                        has: page.getByRole('heading', { name: cardTitle, exact: true }),
                    }).first();
                    await expect(card).toBeVisible();

                    const hubDownloads: Record<string, {
                        format: string;
                        filename: string | null;
                        sha256: string | null;
                        bytes: number;
                        url: string | null;
                        status: number | null;
                        status_text: string | null;
                        content_type: string | null;
                        error?: string;
                        error_snippet?: string | null;
                    }> = {};

                    // 6. Click real BUTTONS on Reports Index (DOCX, PDF, CSV)
                    for (const format of ['docx', 'pdf', 'csv']) {
                        const downloadButton = card.getByRole('button', { name: new RegExp(`^\\s*${format}\\s*$`, 'i') });
                        await expect(downloadButton).toBeVisible();

                        let capturedResponse: {
                            status: number;
                            statusText: string;
                            url: string;
                            contentType?: string;
                            errorSnippet?: string;
                        } | null = null;

                        let rejectOnErrorResponse: ((reason: Error) => void) | null = null;
                        const errorResponsePromise = new Promise<never>((_, reject) => {
                            rejectOnErrorResponse = reject;
                        });

                        const responseListener = async (response: Response) => {
                            try {
                                const respUrl = response.url();
                                if (respUrl.includes(`/reports/export/${reportType}`)) {
                                    const status = response.status();
                                    let errorSnippet: string | undefined;
                                    if (status >= 400) {
                                        try {
                                            const text = await response.text();
                                            errorSnippet = text.slice(0, 1000);
                                        } catch {
                                            // ignore unreadable error body
                                        }
                                    }
                                    capturedResponse = {
                                        status,
                                        statusText: response.statusText(),
                                        url: respUrl,
                                        contentType: response.headers()['content-type'],
                                        ...(errorSnippet ? { errorSnippet } : {}),
                                    };
                                    if (status >= 400 && rejectOnErrorResponse) {
                                        rejectOnErrorResponse(
                                            new Error(`Export request failed with HTTP ${status} (${response.statusText()})${errorSnippet ? `: ${errorSnippet}` : ''}`)
                                        );
                                    }
                                }
                            } catch {
                                // ignore listener errors
                            }
                        };

                        page.on('response', responseListener);

                        let download: Download | null = null;
                        let downloadError: Error | null = null;
                        try {
                            const downloadPromise = page.waitForEvent('download', { timeout: DOWNLOAD_TIMEOUT_MS });
                            const clickPromise = downloadButton.click();
                            const [d] = await Promise.race([
                                Promise.all([downloadPromise, clickPromise]),
                                errorResponsePromise,
                            ]);
                            download = d;
                        } catch (err: unknown) {
                            downloadError = err instanceof Error ? err : new Error(String(err));
                        } finally {
                            page.off('response', responseListener);
                        }

                        if (downloadError || !download) {
                            const errorDetail = capturedResponse
                                ? `HTTP ${capturedResponse.status} (${capturedResponse.statusText}) at ${capturedResponse.url}${capturedResponse.errorSnippet ? ` - body: ${capturedResponse.errorSnippet}` : ''}`
                                : `No HTTP response received within ${DOWNLOAD_TIMEOUT_MS}ms: ${downloadError?.message}`;

                            hubDownloads[format] = {
                                format,
                                filename: null,
                                sha256: null,
                                bytes: 0,
                                url: capturedResponse?.url ?? null,
                                status: capturedResponse?.status ?? null,
                                status_text: capturedResponse?.statusText ?? null,
                                content_type: capturedResponse?.contentType ?? null,
                                error: errorDetail,
                                error_snippet: capturedResponse?.errorSnippet ?? null,
                            };
                            caseEntry.hub_downloads = hubDownloads;

                            throw new Error(`Download failed for format "${format}": ${errorDetail}`);
                        }

                        const downloadUrl = new URL(download.url());
                        expect(downloadUrl.pathname).toBe(`/reports/export/${reportType}`);
                        expect(downloadUrl.searchParams.get('format')).toBe(format);
                        expect(downloadUrl.searchParams.get('cycle_id')).toBe(String(fixtureCase.academic_cycle_id));

                        const suggestedFilename = download.suggestedFilename();
                        expect(suggestedFilename).toContain(reportType);
                        expect(suggestedFilename).toContain(`.${format}`);

                        const savePath = path.join(caseDir, `hub-download.${format}`);
                        await download.saveAs(savePath);

                        const fileBytes = await readFile(savePath);
                        expect(fileBytes.length).toBeGreaterThan(0);
                        const fileSha = createHash('sha256').update(fileBytes).digest('hex');

                        hubDownloads[format] = {
                            format,
                            filename: suggestedFilename,
                            sha256: fileSha,
                            bytes: fileBytes.length,
                            url: download.url(),
                            status: capturedResponse?.status ?? 200,
                            status_text: capturedResponse?.statusText ?? 'OK',
                            content_type: capturedResponse?.contentType ?? null,
                        };
                        caseEntry.hub_downloads = hubDownloads;

                        if (format === 'docx') {
                            expect(fileBytes.subarray(0, 4)).toEqual(Buffer.from([0x50, 0x4b, 0x03, 0x04]));
                            const docxXml = extractDocxXml(savePath);
                            expect(docxXml).toContain('<w:p');
                        } else if (format === 'pdf') {
                            expect(fileBytes.subarray(0, 5)).toEqual(Buffer.from('%PDF-'));
                            const hubPdfText = extractPdfText(savePath);
                            // Unfiltered hub download includes all accepted rows in cycle (including BSN!)
                            expect(hubPdfText).toContain('Mara CurrentOne');
                            expect(hubPdfText).toContain('Noel CurrentTwo');
                            expect(hubPdfText).toContain('RPT-BSN-003');
                        } else if (format === 'csv') {
                            const csvContent = fileBytes.toString('utf8');
                            expect(csvContent).toContain('Mara CurrentOne');
                            expect(csvContent).toContain('Noel CurrentTwo');
                            expect(csvContent).toContain('RPT-BSN-003');
                        }
                    }

                    // 7. Direct Authenticated Export Requests with Program Filter (BSIT)
                    // A. Program-Filtered PDF
                    const pdfParams = new URLSearchParams({
                        format: 'pdf',
                        academic_cycle_id: String(fixtureCase.academic_cycle_id),
                        program_code: 'BSIT',
                        ...(foreignCycleId ? { cycle_id: foreignCycleId } : {}),
                        ...(isPassing ? { cutoff_score: '75' } : {}),
                    });
                    const pdfResp = await page.request.get(`/reports/export/${reportType}?${pdfParams.toString()}`);
                    if (pdfResp.status() !== 200) {
                        const errorSnippet = await pdfResp.text().catch(() => '<unreadable>');
                        throw new Error(`Direct filtered PDF export failed with HTTP ${pdfResp.status()} ${pdfResp.statusText()}: ${errorSnippet.slice(0, 1000)}`);
                    }
                    expect(pdfResp.status()).toBe(200);
                    expect(pdfResp.headers()['content-type']).toContain('application/pdf');

                    const filteredPdfBytes = await pdfResp.body();
                    expect(filteredPdfBytes.subarray(0, 5)).toEqual(Buffer.from('%PDF-'));
                    const filteredPdfSha = createHash('sha256').update(filteredPdfBytes).digest('hex');
                    const filteredPdfPath = path.join(caseDir, 'program-filtered.pdf');
                    await writeFile(filteredPdfPath, filteredPdfBytes);

                    const filteredPdfText = extractPdfText(filteredPdfPath);
                    // Must contain layout marker
                    expect(filteredPdfText.toLowerCase()).toContain(fixtureCase.marker.toLowerCase());
                    // Must contain the two BSIT qualifiers/passers
                    expect(filteredPdfText).toContain('Mara CurrentOne');
                    expect(filteredPdfText).toContain('Noel CurrentTwo');
                    expect(filteredPdfText).toContain('RPT-CURRENT-001');
                    expect(filteredPdfText).toContain('RPT-CURRENT-002');
                    // Must NOT contain BSN (filtered out by BSIT program filter!)
                    expect(filteredPdfText).not.toContain('RPT-BSN-003');
                    expect(filteredPdfText).not.toContain('NursingContrast');
                    expect(filteredPdfText).not.toContain('RPT-PENDING-004');
                    // Must NOT contain foreign cycle rows
                    expect(filteredPdfText).not.toContain('RPT-FOREIGN-001');
                    expect(filteredPdfText).not.toContain('RPT-FOREIGN-002');
                    // Must NOT contain unresolved macros
                    expect(filteredPdfText).not.toContain('{{');

                    // B. Program-Filtered DOCX
                    const docxParams = new URLSearchParams({
                        format: 'docx',
                        academic_cycle_id: String(fixtureCase.academic_cycle_id),
                        program_code: 'BSIT',
                    });
                    const docxResp = await page.request.get(`/reports/export/${reportType}?${docxParams.toString()}`);
                    if (docxResp.status() !== 200) {
                        const errorSnippet = await docxResp.text().catch(() => '<unreadable>');
                        throw new Error(`Direct filtered DOCX export failed with HTTP ${docxResp.status()} ${docxResp.statusText()}: ${errorSnippet.slice(0, 1000)}`);
                    }
                    expect(docxResp.status()).toBe(200);
                    expect(docxResp.headers()['content-type']).toContain('application/vnd.openxmlformats-officedocument.wordprocessingml.document');

                    const filteredDocxBytes = await docxResp.body();
                    expect(filteredDocxBytes.subarray(0, 4)).toEqual(Buffer.from([0x50, 0x4b, 0x03, 0x04]));
                    const filteredDocxSha = createHash('sha256').update(filteredDocxBytes).digest('hex');
                    const filteredDocxPath = path.join(caseDir, 'program-filtered.docx');
                    await writeFile(filteredDocxPath, filteredDocxBytes);

                    const filteredDocxXml = extractDocxXml(filteredDocxPath);
                    expect(filteredDocxXml).toContain('<w:p');
                    expect(filteredDocxXml).toContain('<w:tbl');
                    if (reportSource === 'docx') {
                        expect(filteredDocxXml.toLowerCase()).toContain(fixtureCase.marker.toLowerCase());
                        expect(filteredDocxXml).toContain('Mara CurrentOne');
                        expect(filteredDocxXml).toContain('Noel CurrentTwo');
                    } else {
                        // Builder source DOCX output uses standard default document without Word compiler
                        expect(filteredDocxXml).not.toContain(fixtureCase.marker);
                        expect(filteredDocxXml).toContain(isPassing ? 'Passing Candidates Census' : 'Course Quota Qualifiers');
                    }

                    // C. Program-Filtered CSV
                    const csvParams = new URLSearchParams({
                        format: 'csv',
                        academic_cycle_id: String(fixtureCase.academic_cycle_id),
                        program_code: 'BSIT',
                    });
                    const csvResp = await page.request.get(`/reports/export/${reportType}?${csvParams.toString()}`);
                    if (csvResp.status() !== 200) {
                        const errorSnippet = await csvResp.text().catch(() => '<unreadable>');
                        throw new Error(`Direct filtered CSV export failed with HTTP ${csvResp.status()} ${csvResp.statusText()}: ${errorSnippet.slice(0, 1000)}`);
                    }
                    expect(csvResp.status()).toBe(200);
                    expect(csvResp.headers()['content-type']).toContain('text/csv');

                    const filteredCsvText = (await csvResp.body()).toString('utf8');
                    const filteredCsvPath = path.join(caseDir, 'program-filtered.csv');
                    await writeFile(filteredCsvPath, filteredCsvText);

                    if (isPassing) {
                        expect(filteredCsvText).toContain('"Reference Code"');
                        expect(filteredCsvText).toContain('"Applicant Name"');
                        expect(filteredCsvText).toContain('Composite Score');
                    } else {
                        expect(filteredCsvText).toContain('Reference Code');
                        expect(filteredCsvText).toContain('Applicant Name');
                        expect(filteredCsvText).toContain('Admission Status');
                    }
                    expect(filteredCsvText).toContain('RPT-CURRENT-001');
                    expect(filteredCsvText).toContain('Mara CurrentOne');
                    expect(filteredCsvText).toContain('RPT-CURRENT-002');
                    expect(filteredCsvText).toContain('Noel CurrentTwo');
                    expect(filteredCsvText).not.toContain('RPT-BSN-003');
                    expect(filteredCsvText).not.toContain('RPT-FOREIGN-001');

                    caseEntry.filtered_exports = {
                        pdf: { status: pdfResp.status(), sha256: filteredPdfSha, bytes: filteredPdfBytes.length, file: 'program-filtered.pdf' },
                        docx: { status: docxResp.status(), sha256: filteredDocxSha, bytes: filteredDocxBytes.length, file: 'program-filtered.docx' },
                        csv: { status: csvResp.status(), file: 'program-filtered.csv' },
                    };

                    // 8. Opposite Actor Wrong Office Independent Auth Deny
                    let oppositeContext: BrowserContext | null = null;
                    try {
                        oppositeContext = await browser.newContext({ serviceWorkers: 'block' });
                        await oppositeContext.route('**/*', async (route) => {
                            const requestUrl = new URL(route.request().url());
                            if (['data:', 'blob:', 'chrome-extension:', 'about:'].includes(requestUrl.protocol)
                                || requestUrl.origin === parsedBaseUrl.origin) {
                                await route.continue();
                                return;
                            }
                            await route.abort('blockedbyclient');
                        });
                        const oppositePage = await oppositeContext.newPage();
                        await oppositePage.goto(new URL('/login', baseUrl!).toString(), { waitUntil: 'domcontentloaded' });
                        await oppositePage.locator('#email').fill(oppositeActor.email);
                        await oppositePage.locator('#password').fill(oppositeActor.password);
                        await oppositePage.locator('button[type="submit"]').click();
                        await oppositePage.waitForURL((url) => url.pathname !== '/login', { timeout: 20_000 });

                        const denyPdfResp = await oppositePage.request.get(`/reports/export/${reportType}?format=pdf`);
                        expect(denyPdfResp.status()).toBe(403);
                        const denyPdfBytes = await denyPdfResp.body();
                        expect(denyPdfBytes.subarray(0, 5)).not.toEqual(Buffer.from('%PDF-'));

                        const denyCsvResp = await oppositePage.request.get(`/reports/export/${reportType}?format=csv`);
                        expect(denyCsvResp.status()).toBe(403);

                        const denyDocxResp = await oppositePage.request.get(`/reports/export/${reportType}?format=docx`);
                        expect(denyDocxResp.status()).toBe(403);

                        caseEntry.wrong_office_denial = {
                            actor_email: oppositeActor.email,
                            status: 403,
                            rendered_pdf_blocked: true,
                        };
                    } finally {
                        if (oppositeContext) {
                            await oppositeContext.close().catch(() => {});
                        }
                    }

                    // 9. Foreign Cycle Legitimate Filter (Does NOT invent scope 403)
                    let foreignCycleFilterOk = false;
                    if (foreignCycleId) {
                        const foreignResp = await page.request.get(`/reports/export/${reportType}?format=pdf&cycle_id=${foreignCycleId}&program_code=BSIT`);
                        expect(foreignResp.status()).toBe(200);
                        expect(foreignResp.headers()['content-type']).toContain('application/pdf');
                        const foreignPdfBytes = await foreignResp.body();
                        expect(foreignPdfBytes.subarray(0, 5)).toEqual(Buffer.from('%PDF-'));
                        foreignCycleFilterOk = true;
                    }

                    caseEntry.foreign_cycle_filter = {
                        cycle_id: foreignCycleId,
                        status: 200,
                        filter_accepted: foreignCycleFilterOk,
                    };

                    caseEntry.outcome = 'PASS';
                } catch (err: unknown) {
                    caseEntry.outcome = 'FAIL';
                    caseEntry.error = err instanceof Error ? err.message : String(err);
                    caseErrors.push(err instanceof Error ? err : new Error(String(err)));
                } finally {
                    if (context) {
                        await context.close().catch(() => {});
                    }
                    await persistLedger();
                }
            }
        } finally {
            await persistLedger();
        }

        if (caseErrors.length > 0) {
            throw new Error(`Operational report test failed (${caseErrors.length} error(s)):\n${caseErrors.map((e, idx) => `[Case ${idx + 1}] ${e.message}`).join('\n')}`);
        }
    });
});
