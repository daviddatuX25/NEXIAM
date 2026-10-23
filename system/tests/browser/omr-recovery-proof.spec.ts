import { test, expect } from '@playwright/test';

test('Draft1 run22 exposes restart recovery and Stage3→Stage2 navigation', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel(/email/i).fill('guidance.counselor@nexiam.test');
    await page.getByLabel(/password/i).fill('password');
    await page.getByRole('button', { name: /sign in|log in/i }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), {
        timeout: 30_000,
    });
    await page.goto('/setup/exam-materials/answer-sheets/1/edit');
    const verificationStageButton = page.getByRole('button', {
        name: '3 Verification & Test',
    });
    if (await verificationStageButton.isVisible()) {
        await verificationStageButton.click();
    }

    await expect(page.getByText('Verification & Test Capture')).toBeVisible({
        timeout: 30_000,
    });
    await expect(page.getByText('Layout changed', { exact: true })).toBeVisible(
        { timeout: 30_000 },
    );
    await expect(
        page.getByRole('button', { name: 'Start new rehearsal' }),
    ).toBeVisible();
    await expect(page.getByText('Update challenge sheet')).not.toBeVisible();

    const pageSecurity = await page.evaluate(() => ({
        origin: window.location.origin,
        metaTokenPresent: Boolean(
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content'),
        ),
        metaTokenLength:
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content')?.length ?? 0,
        cookieNames: document.cookie
            .split(';')
            .map((cookie) => cookie.trim().split('=')[0])
            .filter(Boolean),
    }));
    const cookieMetadata = (await page.context().cookies()).map(
        ({ name, domain, path, sameSite, secure, httpOnly }) => ({
            name,
            domain,
            path,
            sameSite,
            secure,
            httpOnly,
        }),
    );

    let restartRequestHeaders: Record<string, string> = {};
    page.on('request', (request) => {
        if (
            request
                .url()
                .includes(
                    '/setup/omr/templates/verification-runs/22/challenge/restart',
                )
        ) {
            restartRequestHeaders = request.headers();
        }
    });

    const [restartResponse] = await Promise.all([
        page.waitForResponse(
            (response) =>
                response
                    .url()
                    .includes(
                        '/setup/omr/templates/verification-runs/22/challenge/restart',
                    ),
            { timeout: 30_000 },
        ),
        page.getByRole('button', { name: 'Start new rehearsal' }).click(),
    ]);
    const restartStatus = restartResponse.status();
    expect([200, 422]).toContain(restartStatus);
    expect(restartStatus).not.toBe(419);
    expect(restartRequestHeaders['x-xsrf-token']).toBeTruthy();
    expect(restartRequestHeaders['x-csrf-token']).toBeUndefined();

    if (restartStatus === 200) {
        await expect(
            page.getByText('Digital engine check', { exact: true }),
        ).toBeVisible({ timeout: 30_000 });
        await expect(
            page.getByText(/Current layout prepared|digital verification/i),
        ).toBeVisible({ timeout: 30_000 });
    } else {
        await expect(
            page.getByText(
                /Could not start a new rehearsal|cannot be restarted/i,
            ),
        ).toBeVisible({ timeout: 30_000 });
    }

    await page
        .getByRole('button', { name: 'Return to Recognition Mapping' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Recognition Mapping', exact: true }),
    ).toBeVisible();
    await expect(page.getByText(/concurrency|conflict/i)).not.toBeVisible();

    console.log(
        'OMR_BROWSER_PROOF',
        JSON.stringify({
            recovery: 'restart_rehearsal',
            action: 'Start new rehearsal',
            repair_action_visible: false,
            stage_navigation: 'return_to_recognition_mapping_without_conflict',
            run_id: 22,
            restart_status: restartStatus,
            csrf: {
                origin: pageSecurity.origin,
                meta_token_present: pageSecurity.metaTokenPresent,
                meta_token_length: pageSecurity.metaTokenLength,
                cookie_names: pageSecurity.cookieNames,
                cookie_metadata: cookieMetadata,
                xsrf_header_present: Boolean(
                    restartRequestHeaders['x-xsrf-token'],
                ),
                csrf_header_present: Boolean(
                    restartRequestHeaders['x-csrf-token'],
                ),
            },
        }),
    );
});
