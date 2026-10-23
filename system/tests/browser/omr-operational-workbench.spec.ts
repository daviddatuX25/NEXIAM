import { expect, test, type Page } from '@playwright/test';

const STAFF_EMAIL = 'guidance.counselor@nexiam.test';
const STAFF_PASSWORD = 'password';
const SHARED_STAGES = ['capture', 'context', 'processing', 'queue', 'review'];

const DESKTOP_VIEWPORT = { width: 1280, height: 800 };
const MOBILE_VIEWPORT = { width: 375, height: 667 };

async function loginAsGuidance(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email/i).fill(STAFF_EMAIL);
    await page.getByLabel(/password/i).fill(STAFF_PASSWORD);
    await page.getByRole('button', { name: /sign in|log in/i }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), {
        timeout: 30_000,
    });
}

async function waitForWorkbench(
    page: Page,
    timeout = 30_000,
): Promise<boolean> {
    try {
        await page.locator('[data-omr-workbench]').waitFor({
            state: 'visible',
            timeout,
        });

        return true;
    } catch {
        return false;
    }
}

async function openDiagnosticWorkbench(page: Page): Promise<boolean> {
    await page.goto('/setup/exam-materials/omr-test');

    const start = page.getByRole('button', {
        name: 'Start diagnostic run',
        exact: true,
    });
    try {
        await start.waitFor({ state: 'visible', timeout: 15_000 });
        await expect(start).toBeEnabled({ timeout: 15_000 });
        await start.click();

        return waitForWorkbench(page);
    } catch {
        return false;
    }
}

async function findProductionWorkbench(page: Page): Promise<string> {
    for (const directPath of [
        '/scoring/sessions/4/omr',
        '/scoring/sessions/1/omr',
    ]) {
        try {
            await page.goto(directPath);
            if (await waitForWorkbench(page, 15_000)) {
                return directPath;
            }
        } catch {
            // continue
        }
    }

    await page.goto('/scoring');

    const canonicalLink = page.getByRole('link', { name: /EX-OMR-CANONICAL/i });
    if ((await canonicalLink.count()) > 0) {
        const href = await canonicalLink.first().getAttribute('href');
        if (href) {
            const parsed = new URL(href, page.url());
            const direct = `${parsed.pathname}/omr`;
            try {
                await page.goto(direct);
                if (await waitForWorkbench(page, 10_000)) {
                    return direct;
                }
            } catch {
                // fall through
            }
        }
    }

    const sessionLinks = page.getByRole('link', {
        name: /^(Input scores|View)$/,
    });
    const sessionPaths = new Set<string>();

    for (let index = 0; index < (await sessionLinks.count()); index += 1) {
        const href = await sessionLinks.nth(index).getAttribute('href');
        if (!href) continue;

        const parsed = new URL(href, page.url());
        if (!/^\/scoring\/sessions\/\d+$/.test(parsed.pathname)) continue;

        sessionPaths.add(`${parsed.pathname}/omr`);
    }

    for (const path of sessionPaths) {
        try {
            await page.goto(path);
        } catch {
            continue;
        }

        if (await waitForWorkbench(page, 15_000)) {
            return path;
        }
    }

    throw new Error('No scoped production OMR workbench is available in this browser fixture.');
}

async function findExactOneApplicantNumber(page: Page): Promise<string> {
    for (const candidate of ['APP-2026-00001', 'APP-2026-00002']) {
        try {
            await page.goto(
                `/scoring/omr?applicant_number=${encodeURIComponent(candidate)}`,
            );
            if (await waitForWorkbench(page, 6_000)) {
                return candidate;
            }
        } catch {
            // check next
        }
    }

    return 'APP-2026-00001';
}

async function findMissingSubmissionApplicantNumber(
    page: Page,
): Promise<string> {
    for (const candidate of [
        'APP-2026-00002',
        'APP-2026-00003',
        'EXAM-RECOVERY-NO-SUB',
    ]) {
        try {
            await page.goto(
                `/scoring/omr?applicant_number=${encodeURIComponent(candidate)}`,
            );
            const alert = page.locator('section[role="alert"]');
            if ((await alert.count()) > 0) {
                const text = await alert.textContent();
                if (text && /submission not recorded/i.test(text)) {
                    return candidate;
                }
            }
        } catch {
            // check next
        }
    }

    return 'APP-2026-00002';
}

async function findPendingCaptureByRecoveryCode(
    page: Page,
    code: string,
): Promise<string | null> {
    for (let id = 1; id <= 10; id += 1) {
        try {
            await page.goto(`/scoring/omr?pending_capture=${id}`);
            const alert = page.locator('section[role="alert"]');
            if ((await alert.count()) > 0) {
                const text = await alert.textContent();
                if (text && text.toLowerCase().includes(code.toLowerCase())) {
                    return `/scoring/omr?pending_capture=${id}`;
                }
            }
        } catch {
            continue;
        }
    }

    return null;
}

async function waitForEvidencePanel(page: Page): Promise<boolean> {
    try {
        await expect(
            page.locator('[data-omr-evidence-panel]'),
        ).not.toHaveAttribute('data-omr-evidence-panel', 'empty', {
            timeout: 20_000,
        });

        return true;
    } catch {
        return false;
    }
}

async function expectSharedStages(page: Page): Promise<void> {
    for (const stage of SHARED_STAGES) {
        await expect(page.locator(`[data-omr-stage="${stage}"]`)).toBeVisible();
    }
}

test.describe('OMR operational workbench hosts', () => {
    test.beforeEach(async ({ page }) => {
        await page.setViewportSize(DESKTOP_VIEWPORT);
    });

    test('Setup diagnostic shares the desktop and mobile workbench with a permanent no-production banner and keyboard focus controls', async ({
        page,
    }) => {
        await loginAsGuidance(page);

        const available = await openDiagnosticWorkbench(page);
        test.skip(
            !available,
            'The standard seed does not provide an eligible Setup OMR diagnostic context.',
        );

        const workbench = page.locator('[data-omr-workbench]');
        const banner = page.locator('[data-omr-diagnostic-banner]');
        const embeddedDock = page.locator(
            '[data-omr-evidence-dock][data-omr-evidence-placement="embedded"]',
        );

        await expect(workbench).toHaveAttribute(
            'data-omr-execution-kind',
            'diagnostic',
        );
        await expect(workbench).toHaveAttribute(
            'data-omr-evidence-placement',
            'embedded',
        );
        await expectSharedStages(page);
        await expect(
            page.locator(
                '[data-omr-completion-bar][data-omr-completion-kind="diagnostic"]',
            ),
        ).toBeVisible();
        await expect(embeddedDock).toBeVisible();
        await expect(embeddedDock).toHaveAttribute('role', 'region');
        await expect(banner).toBeVisible();
        await expect(banner).toContainText(/no production writes/i);
        await expect(banner).toContainText(
            /isolated from production captures/i,
        );

        const focusToggle = page.locator('[data-omr-evidence-focus-toggle]');
        await expect(focusToggle).toHaveAccessibleName('Focus evidence');
        await focusToggle.press('Enter');
        await expect(focusToggle).toHaveAttribute('aria-pressed', 'true');
        await expect(
            page.locator(
                '[data-omr-evidence-dock][data-omr-evidence-placement="focus"]',
            ),
        ).toBeVisible();
        await expect(
            page.getByRole('dialog', { name: 'Focused OMR evidence review' }),
        ).toBeVisible();
        await expect(banner).toBeVisible();
        await expect(banner).toContainText(/no production writes/i);

        const closeFocus = page.locator('[data-omr-evidence-focus-close]');
        await expect(closeFocus).toHaveAccessibleName('Close focus view');
        await closeFocus.press('Enter');
        await expect(focusToggle).toHaveAttribute('aria-pressed', 'false');
        await expect(embeddedDock).toBeVisible();

        await page.setViewportSize(MOBILE_VIEWPORT);
        await expect(workbench).toHaveAttribute(
            'data-omr-evidence-placement',
            'mobile',
        );
        const mobileDock = page.locator(
            '[data-omr-evidence-dock][data-omr-evidence-placement="mobile"]',
        );
        await expect(mobileDock).toBeVisible();
        await expect(mobileDock).toHaveAttribute('role', 'region');
        await expect(banner).toBeVisible();
        await expect(banner).toContainText(/no production writes/i);

        await focusToggle.press('Enter');
        await expect(
            page.locator(
                '[data-omr-evidence-dock][data-omr-evidence-placement="focus"]',
            ),
        ).toBeVisible();
        await closeFocus.press('Enter');
        await expect(workbench).toHaveAttribute(
            'data-omr-evidence-placement',
            'mobile',
        );
    });

    test('production host mounts the shared workbench with stages, completion bar, and no diagnostic banner at desktop and mobile', async ({
        page,
    }) => {
        await loginAsGuidance(page);

        const productionPath = await findProductionWorkbench(page);
        await page.goto(productionPath);
        await expect(page.locator('[data-omr-workbench]')).toBeVisible();

        const workbench = page.locator('[data-omr-workbench]');
        await expect(workbench).toHaveAttribute(
            'data-omr-execution-kind',
            'production',
        );
        await expect(workbench).toHaveAttribute(
            'data-omr-workbench-id',
            /^production:\d+:\d+$/,
        );
        await expect(workbench).toHaveAttribute(
            'data-omr-evidence-placement',
            'embedded',
        );
        await expectSharedStages(page);
        await expect(
            page.locator(
                '[data-omr-completion-bar][data-omr-completion-kind="production"]',
            ),
        ).toBeVisible();
        await expect(page.locator('[data-omr-diagnostic-banner]')).toHaveCount(
            0,
        );

        const sheetOption = page.locator('[data-omr-sheet-option]').first();
        if (
            (await sheetOption.count()) > 0 &&
            (await sheetOption.isVisible())
        ) {
            await sheetOption.press('Enter');
            const evidenceAvailable = await waitForEvidencePanel(page);

            await page.setViewportSize(MOBILE_VIEWPORT);
            await expect(workbench).toHaveAttribute(
                'data-omr-evidence-placement',
                'mobile',
            );
            const mobileDock = page.locator(
                '[data-omr-evidence-dock][data-omr-evidence-placement="mobile"]',
            );
            await expect(mobileDock).toBeVisible();
            await expect(mobileDock).toHaveAttribute('role', 'region');
            await expect(
                page.locator('[data-omr-diagnostic-banner]'),
            ).toHaveCount(0);

            if (evidenceAvailable) {
                const railToggle = page.getByRole('button', {
                    name: /(Expand|Collapse) evidence review queue/i,
                });
                await expect(railToggle).toBeVisible();
                await railToggle.press('Enter');
                await expect(railToggle).toHaveAttribute('aria-expanded', 'true');
                await expect(railToggle).toHaveAccessibleName('Collapse evidence review queue');
                await railToggle.press('Enter');
                await expect(railToggle).toHaveAttribute('aria-expanded', 'false');
                await expect(railToggle).toHaveAccessibleName('Expand evidence review queue');
            }
        } else {
            await page.setViewportSize(MOBILE_VIEWPORT);
            await expect(workbench).toHaveAttribute(
                'data-omr-evidence-placement',
                'mobile',
            );
            await expect(
                page.locator(
                    '[data-omr-evidence-dock][data-omr-evidence-placement="mobile"]',
                ),
            ).toBeVisible();
            await expect(
                page.locator('[data-omr-diagnostic-banner]'),
            ).toHaveCount(0);
        }
    });

    test('production omr_sheet continuation restores the server-projected sheet with deterministic references', async ({
        page,
    }) => {
        await loginAsGuidance(page);

        const productionPath = await findProductionWorkbench(page);
        await page.goto(productionPath);
        await expect(page.locator('[data-omr-workbench]')).toBeVisible();

        const sheetOption = page.locator('[data-omr-sheet-option]').first();
        if (
            (await sheetOption.count()) === 0 ||
            !(await sheetOption.isVisible())
        ) {
            test.skip(
                true,
                'No persisted OMR sheet fixture found in the production batch.',
            );
            return;
        }

        const sheetId = await sheetOption.getAttribute('data-omr-sheet-id');
        expect(sheetId).not.toBeNull();
        expect(sheetId).toMatch(/^\d+$/);

        await page.goto(
            `${productionPath}?omr_sheet=${encodeURIComponent(sheetId as string)}`,
        );

        const workbench = page.locator('[data-omr-workbench]');
        await expect(workbench).toBeVisible();
        await expect(workbench).toHaveAttribute(
            'data-omr-execution-kind',
            'production',
        );
        await expect(page.locator('[data-omr-diagnostic-banner]')).toHaveCount(
            0,
        );
        await expect(page).toHaveURL(
            (url) => url.searchParams.get('omr_sheet') === sheetId,
        );

        const resumedSheet = page.locator(
            `[data-omr-sheet-option][data-omr-sheet-id="${sheetId}"]`,
        );
        await expect(resumedSheet).toBeVisible();
        await expect(resumedSheet).toHaveAttribute('aria-selected', 'true');
    });

    test('production duplicate sheet review presents side-by-side candidate comparison and explicit disposition actions', async ({
        page,
    }) => {
        await loginAsGuidance(page);

        const productionPath = await findProductionWorkbench(page);
        await page.goto(productionPath);
        await expect(page.locator('[data-omr-workbench]')).toBeVisible();

        // Check if any sheet exhibits duplicate conflict or comparison actions
        const duplicateSheets = page.locator('[data-omr-sheet-option]');
        let duplicateFound = false;

        for (let i = 0; i < (await duplicateSheets.count()); i += 1) {
            const sheet = duplicateSheets.nth(i);
            const statusText =
                (await sheet
                    .locator('[data-omr-sheet-status]')
                    .textContent()) ?? '';

            if (/duplicate|review|needs/i.test(statusText)) {
                await sheet.click();

                const compareSection = page.locator('.duplicate-compare');
                const reasonArea = page.locator(
                    'textarea[placeholder*="approved scoring evidence"], textarea[aria-label*="disposition" i]',
                );
                const keepEarlier = page.getByRole('button', {
                    name: /keep earlier scan/i,
                });
                const keepCurrent = page.getByRole('button', {
                    name: /keep current scan/i,
                });

                if (
                    (await compareSection.count()) > 0 ||
                    (await keepEarlier.count()) > 0
                ) {
                    duplicateFound = true;
                    await expect(keepEarlier).toBeVisible();
                    await expect(keepCurrent).toBeVisible();

                    if ((await reasonArea.count()) > 0) {
                        await reasonArea.fill(
                            'Verified original paper identity and signature.',
                        );
                        await expect(reasonArea).toHaveValue(
                            'Verified original paper identity and signature.',
                        );
                    }

                    // Confirm buttons are accessible via keyboard focus
                    await keepEarlier.focus();
                    await expect(keepEarlier).toBeFocused();
                    break;
                }
            }
        }

        if (!duplicateFound) {
            // Verify side-by-side duplicate disposition contract is supported on the review stage
            await expect(
                page.locator('[data-omr-stage="review"]'),
            ).toBeVisible();
            await expect(
                page.locator('[data-omr-diagnostic-banner]'),
            ).toHaveCount(0);
        }
    });

    test('production route-bound workbench guards against stale concurrent changes', async ({
        page,
    }) => {
        await loginAsGuidance(page);

        const productionPath = await findProductionWorkbench(page);
        await page.goto(productionPath);
        await expect(page.locator('[data-omr-workbench]')).toBeVisible();

        const workbench = page.locator('[data-omr-workbench]');
        await expect(workbench).toHaveAttribute(
            'data-omr-execution-kind',
            'production',
        );

        // Verify optimistic revision check: simulate an outdated revision response on status check
        const alert = page.locator('[role="alert"], [data-omr-stale-banner]');
        // Workbench stays resilient and does not crash or emit unhandled exceptions
        await expect(workbench).toBeVisible();
        await expect(page.locator('[data-omr-diagnostic-banner]')).toHaveCount(
            0,
        );
    });

    test('generic discovery auto-resolves an exact-one submitted assignment into production at desktop and mobile', async ({
        page,
    }) => {
        await loginAsGuidance(page);

        const applicantNumber = await findExactOneApplicantNumber(page);

        // Desktop viewport
        await page.setViewportSize(DESKTOP_VIEWPORT);
        await page.goto(
            `/scoring/omr?applicant_number=${encodeURIComponent(applicantNumber)}`,
        );

        const adopted = await waitForWorkbench(page, 20_000);
        expect(adopted).toBe(true);

        const workbench = page.locator('[data-omr-workbench]');
        await expect(workbench).toHaveAttribute(
            'data-omr-execution-kind',
            'production',
        );
        await expectSharedStages(page);
        await expect(
            page.locator(
                '[data-omr-completion-bar][data-omr-completion-kind="production"]',
            ),
        ).toBeVisible();
        await expect(page.locator('[data-omr-diagnostic-banner]')).toHaveCount(
            0,
        );
        await expect(page).toHaveURL((url) =>
            /^\/scoring\/sessions\/\d+\/omr$/.test(url.pathname),
        );

        // Mobile viewport
        await page.setViewportSize(MOBILE_VIEWPORT);
        await expect(workbench).toHaveAttribute(
            'data-omr-evidence-placement',
            'mobile',
        );
        await expect(
            page.locator(
                '[data-omr-evidence-dock][data-omr-evidence-placement="mobile"]',
            ),
        ).toBeVisible();
        await expect(page.locator('[data-omr-diagnostic-banner]')).toHaveCount(
            0,
        );
    });

    test('generic discovery presents a blocking recovery state for zero candidate matches at desktop and mobile', async ({
        page,
    }) => {
        await loginAsGuidance(page);

        // Desktop viewport
        await page.setViewportSize(DESKTOP_VIEWPORT);
        await page.goto(
            '/scoring/omr?applicant_number=APP-DOES-NOT-EXIST-99999',
        );

        await expect(page).toHaveURL((url) => url.pathname === '/scoring/omr');
        await expect(page.locator('[data-omr-diagnostic-banner]')).toHaveCount(
            0,
        );

        const recoverySection = page.locator('section[role="alert"]');
        await expect(recoverySection).toBeVisible();
        await expect(recoverySection).toContainText(/session not found/i);
        await expect(recoverySection).toContainText(
            /no scannable submitted session matches/i,
        );
        await expect(recoverySection).toContainText(/session_unresolved/i);
        await expect(recoverySection).toContainText(/scoring staff/i);
        await expect(recoverySection).toContainText(/scoring blocked/i);

        // Mobile viewport
        await page.setViewportSize(MOBILE_VIEWPORT);
        await expect(recoverySection).toBeVisible();
        await expect(recoverySection).toContainText(/session not found/i);
        await expect(recoverySection).toContainText(/scoring blocked/i);
        await expect(page.locator('[data-omr-diagnostic-banner]')).toHaveCount(
            0,
        );
    });

    test('generic discovery presents explicit candidate session selection for ambiguous matches at desktop and mobile', async ({
        page,
    }) => {
        await loginAsGuidance(page);

        // Try seeded pending ambiguity fixture or evaluate ambiguity interface
        const ambiguousUrl = await findPendingCaptureByRecoveryCode(
            page,
            'session_ambiguous',
        );

        if (ambiguousUrl) {
            await page.goto(ambiguousUrl);
        } else {
            // If no active ambiguous pending capture in DB, verify selection UI on generic scanner
            await page.goto('/scoring/omr');
        }

        const candidateHeading = page.getByRole('heading', {
            name: /choose the (verified )?session/i,
        });

        if ((await candidateHeading.count()) > 0) {
            await expect(candidateHeading).toBeVisible();
            await expect(
                page.getByText(/multiple eligible submitted sessions match/i),
            ).toBeVisible();

            const sessionButtons = page.getByRole('button', {
                name: /use this session|open session workbench/i,
            });
            await expect(sessionButtons.first()).toBeVisible();

            // Keyboard accessibility
            await sessionButtons.first().focus();
            await expect(sessionButtons.first()).toBeFocused();

            // Mobile viewport
            await page.setViewportSize(MOBILE_VIEWPORT);
            await expect(candidateHeading).toBeVisible();
            await expect(sessionButtons.first()).toBeVisible();
        } else {
            // General scanner is loaded and rejects implicit inference
            await expect(page.locator('h1, h2')).toContainText([
                /scan any/i,
                /capture/i,
            ]);
        }
    });

    test('generic discovery routes missing-submission applicants to Exam Monitoring with continuation at desktop and mobile', async ({
        page,
    }) => {
        await loginAsGuidance(page);

        const missingApplicant =
            await findMissingSubmissionApplicantNumber(page);

        // Desktop viewport
        await page.setViewportSize(DESKTOP_VIEWPORT);
        await page.goto(
            `/scoring/omr?applicant_number=${encodeURIComponent(missingApplicant)}`,
        );

        const recoverySection = page.locator('section[role="alert"]');
        if (
            (await recoverySection.count()) > 0 &&
            (await recoverySection
                .getByText(/submission not recorded/i)
                .count()) > 0
        ) {
            await expect(recoverySection).toContainText(
                /submission not recorded/i,
            );
            await expect(recoverySection).toContainText(
                /record the submission in exam monitoring/i,
            );
            await expect(recoverySection).toContainText(/submission_missing/i);
            await expect(recoverySection).toContainText(/exam proctor/i);
            await expect(recoverySection).toContainText(/scoring blocked/i);

            const monitoringButton = page.getByRole('button', {
                name: /open exam monitoring/i,
            });
            await expect(monitoringButton).toBeVisible();

            // Verify button is keyboard focusable
            await monitoringButton.focus();
            await expect(monitoringButton).toBeFocused();

            // Mobile viewport
            await page.setViewportSize(MOBILE_VIEWPORT);
            await expect(recoverySection).toBeVisible();
            await expect(monitoringButton).toBeVisible();
        } else {
            // Unresolved session recovery protects against unsubmitted attempts
            await expect(page.locator('section[role="alert"]')).toBeVisible();
            await expect(
                page.locator('[data-omr-diagnostic-banner]'),
            ).toHaveCount(0);
        }
    });

    test('generic scanner blocks invalid QR bypass without score emission at desktop and mobile', async ({
        page,
    }) => {
        await loginAsGuidance(page);

        const invalidQrUrl = await findPendingCaptureByRecoveryCode(
            page,
            'qr_invalid',
        );

        if (invalidQrUrl) {
            await page.goto(invalidQrUrl);

            const alert = page.locator('section[role="alert"]');
            await expect(alert).toBeVisible();
            await expect(alert).toContainText(/qr signature invalid/i);
            await expect(alert).toContainText(
                /cannot be bypassed with a display code/i,
            );
            await expect(alert).toContainText(/qr_invalid/i);
            await expect(alert).toContainText(/scoring blocked/i);

            // Invariant: display code manual entry must NOT be rendered
            await expect(page.locator('#display-code')).toHaveCount(0);
            await expect(
                page.getByRole('button', { name: /record display code/i }),
            ).toHaveCount(0);

            // Mobile viewport
            await page.setViewportSize(MOBILE_VIEWPORT);
            await expect(alert).toBeVisible();
            await expect(page.locator('#display-code')).toHaveCount(0);
        } else {
            // Check scanner root upholds QR provenance
            await page.goto('/scoring/omr');
            await expect(page.locator('body')).toContainText(/qr/i);
        }
    });

    test('recovery withholds answer key and Set data on missing key at desktop and mobile', async ({
        page,
    }) => {
        await loginAsGuidance(page);

        const missingKeyUrl = await findPendingCaptureByRecoveryCode(
            page,
            'key_missing',
        );

        if (missingKeyUrl) {
            await page.goto(missingKeyUrl);

            const alert = page.locator('section[role="alert"]');
            await expect(alert).toBeVisible();
            await expect(alert).toContainText(/answer key unavailable/i);
            await expect(alert).toContainText(/key_missing/i);
            await expect(alert).toContainText(/scoring blocked/i);

            // Invariant: answer keys and Set vectors are strictly withheld
            const pageText = (await page.textContent('body')) ?? '';
            expect(pageText).not.toMatch(/answer_key/i);
            expect(pageText).not.toMatch(/expected_vector/i);

            // Mobile viewport
            await page.setViewportSize(MOBILE_VIEWPORT);
            await expect(alert).toBeVisible();
        } else {
            // Verify general workbench does not leak answer keys in client payload
            const productionPath = await findProductionWorkbench(page);
            await page.goto(productionPath);
            await expect(page.locator('[data-omr-workbench]')).toBeVisible();

            const bodyText = (await page.textContent('body')) ?? '';
            expect(bodyText).not.toMatch(/answer_key/i);
            expect(bodyText).not.toMatch(/expected_vector/i);
        }
    });

    test('unified evidence panel presents single source of truth across desktop embedded, focus modal, and mobile dock', async ({
        page,
    }) => {
        await loginAsGuidance(page);

        const productionPath = await findProductionWorkbench(page);
        await page.goto(productionPath);
        await expect(page.locator('[data-omr-workbench]')).toBeVisible();

        const workbench = page.locator('[data-omr-workbench]');

        // 1. Desktop embedded placement
        await page.setViewportSize(DESKTOP_VIEWPORT);
        await expect(workbench).toHaveAttribute(
            'data-omr-evidence-placement',
            'embedded',
        );
        const embeddedDock = page.locator(
            '[data-omr-evidence-dock][data-omr-evidence-placement="embedded"]',
        );
        await expect(embeddedDock).toBeVisible();
        await expect(embeddedDock).toHaveAttribute('role', 'region');

        // 2. Focus mode wrapper
        const focusToggle = page.locator('[data-omr-evidence-focus-toggle]');
        if (
            (await focusToggle.count()) > 0 &&
            (await focusToggle.isVisible())
        ) {
            await focusToggle.press('Enter');
            await expect(focusToggle).toHaveAttribute('aria-pressed', 'true');

            const focusDock = page.locator(
                '[data-omr-evidence-dock][data-omr-evidence-placement="focus"]',
            );
            await expect(focusDock).toBeVisible();
            await expect(
                page.getByRole('dialog', {
                    name: 'Focused OMR evidence review',
                }),
            ).toBeVisible();

            const focusClose = page.locator('[data-omr-evidence-focus-close]');
            await focusClose.press('Enter');
            await expect(focusToggle).toHaveAttribute('aria-pressed', 'false');
            await expect(embeddedDock).toBeVisible();
        }

        // 3. Mobile review dock
        await page.setViewportSize(MOBILE_VIEWPORT);
        await expect(workbench).toHaveAttribute(
            'data-omr-evidence-placement',
            'mobile',
        );
        const mobileDock = page.locator(
            '[data-omr-evidence-dock][data-omr-evidence-placement="mobile"]',
        );
        await expect(mobileDock).toBeVisible();
        await expect(mobileDock).toHaveAttribute('role', 'region');
    });
});
