import { expect, test, type Page, type Route } from '@playwright/test';

const sessionPath = '/scoring/sessions/4/omr';
type WorkbenchProjection = Record<string, unknown> & {
    context: Record<string, unknown>;
};
const workbenchPath = `${sessionPath}/batches/1/workbench`;
const image = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
    'base64',
);

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    if (page.url().endsWith('/login')) {
        await page.getByLabel(/email/i).fill('guidance.counselor@nexiam.test');
        await page.getByLabel(/password/i).fill('password');
        await page.getByRole('button', { name: /sign in/i }).click();
        await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    }
}

test('printed QR-disabled page progression and pairing recovery render at desktop and mobile widths', async ({
    page,
}) => {
    await login(page);
    let projection: WorkbenchProjection | null = null;
    await page.addInitScript(() => {
        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: {
                getUserMedia: async () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = 640;
                    canvas.height = 480;
                    const context = canvas.getContext('2d')!;
                    window.setInterval(() => {
                        context.fillStyle = '#252a2c';
                        context.fillRect(0, 0, 640, 480);
                        context.fillStyle = '#fefefe';
                        context.fillRect(155, 30, 330, 420);
                        context.strokeStyle = '#181818';
                        context.lineWidth = 6;
                        context.strokeRect(155, 30, 330, 420);
                    }, 66);
                    return canvas.captureStream(15);
                },
                enumerateDevices: async () => [
                    {
                        deviceId: 'synthetic-back',
                        kind: 'videoinput',
                        label: 'Synthetic back camera',
                        groupId: 'synthetic',
                    },
                ],
                addEventListener: () => {},
                removeEventListener: () => {},
            },
        });
    });
    let pending: Record<string, unknown> | null = null;
    const uploadedPages: number[] = [];

    // Browser-only contract fixture: the real nested HTTP authority is exercised in Pest.
    // Do not write a fake qualification or pending set into the shared demo database.
    await page.route(`**${sessionPath}`, async (route) => {
        const response = await route.fetch();
        const html = await response.text();
        const transformed = html.replace(
            /(<script data-page="app" type="application\/json">)([\s\S]*?)(<\/script>)/,
            (_match, open: string, serialized: string, close: string) => {
                const pageData = JSON.parse(serialized);
                pageData.props.material.qr_mode = 'qr_disabled';
                pageData.props.material.registration_mode = 'document_bound';
                pageData.props.batch.template.page_count = 2;
                pageData.props.qr_disabled_issuances = [
                    {
                        id: 88,
                        issuance_code: 'BROWSER-PRINTED-88',
                        page_count: 2,
                        intended_copy_count: 2,
                        remaining_claims: 2,
                        copy_policy: 'unique_instance',
                    },
                ];
                projection = pageData.props
                    .workbench_projection as WorkbenchProjection;
                return `${open}${JSON.stringify(pageData).replaceAll('<', '\\u003C')}${close}`;
            },
        );
        await route.fulfill({ response, body: transformed });
    });

    const reply = async (route: Route) => {
        if (!projection)
            throw new Error('Production projection was not loaded');
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                ...projection,
                context: { ...projection.context, pending_capture: pending },
            }),
        });
    };
    await page.route(`**${workbenchPath}`, reply);
    await page.route(`**${workbenchPath}/pending-capture`, async (route) => {
        pending = {
            id: '00000000-0000-4000-8000-000000000088',
            revision: 1,
            status: 'pending',
            page_count: 2,
            active_page_no: 1,
            pairing_code: null,
            adopted_sheet_id: null,
            pages: [
                {
                    page_no: 1,
                    active: true,
                    capture_count: 0,
                    detected_applicant_number: null,
                },
                {
                    page_no: 2,
                    active: false,
                    capture_count: 0,
                    detected_applicant_number: null,
                },
            ],
        };
        await reply(route);
    });
    await page.route(`**${workbenchPath}/capture`, async (route) => {
        const body = route.request().postData() ?? '';
        const pageNo = Number(body.match(/name="page_no"\r\n\r\n(\d+)/)?.[1]);
        uploadedPages.push(pageNo);
        if (!pending || ![1, 2].includes(pageNo))
            throw new Error('Unexpected page upload');
        if (pageNo === 1) {
            pending.pages[0] = {
                page_no: 1,
                active: false,
                capture_count: 1,
                detected_applicant_number: '123456',
            };
            pending.pages[1].active = true;
            pending.active_page_no = 2;
            pending.revision = 2;
        } else if (pending.pages[1].capture_count === 0) {
            pending.pages[1] = {
                page_no: 2,
                active: false,
                capture_count: 1,
                detected_applicant_number: '654321',
            };
            pending.status = 'captured';
            pending.pairing_code = 'pairing_conflict';
            pending.revision = 4;
        } else {
            expect(body).toContain('Page 2 belongs to another examinee');
            pending.pages[1] = {
                page_no: 2,
                active: false,
                capture_count: 2,
                detected_applicant_number: '123456',
            };
            pending.status = 'ready';
            pending.pairing_code = null;
            pending.revision = 6;
        }
        await reply(route);
    });
    await page.route(
        `**${workbenchPath}/pending-capture/adopt`,
        async (route) => {
            if (!pending || pending.status !== 'ready')
                throw new Error('Incomplete set cannot be adopted');
            pending.adopted_sheet_id = 99;
            if (projection)
                projection = {
                    ...projection,
                    revision: Number(projection.revision) + 1,
                };
            await reply(route);
        },
    );

    await page.goto(sessionPath);
    const capture = page.getByRole('region', {
        name: 'QR-disabled printed issuance capture',
    });
    await expect(capture).toBeVisible();
    await capture.locator('#qr-disabled-issuance').selectOption('88');
    await page.getByRole('button', { name: 'Start pending Page 1' }).click();
    const progress = page.getByRole('region', {
        name: 'Current examinee page progress',
    });
    await expect(progress).toContainText('0 of 2 pages captured');
    const hud = page.locator('[data-omr-viewfinder-hud]');
    await expect(hud).toBeVisible();
    await expect(page.locator('[data-omr-viewfinder-polygon]')).toBeVisible();
    await page.getByRole('button', { name: 'Manual', exact: true }).click();
    await expect(hud).toContainText('Manual shutter ready');
    await page.getByRole('button', { name: 'Auto', exact: true }).click();
    await page.setViewportSize({ width: 820, height: 900 });
    await expect(hud).toBeVisible();
    await page.setViewportSize({ width: 1440, height: 900 });

    async function uploadPage(): Promise<void> {
        const chooser = page.waitForEvent('filechooser');
        await page
            .getByRole('button', { name: /Upload (one page image|image file)/ })
            .click();
        await (
            await chooser
        ).setFiles({ name: 'page.png', mimeType: 'image/png', buffer: image });
    }

    await uploadPage();
    await expect(progress).toContainText('1 of 2 pages captured');
    await expect(progress).toContainText('Page 2');
    await expect(progress).toContainText('123456');
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(progress).toBeVisible();
    await expect(hud).toBeVisible();
    await uploadPage();
    await expect(progress.getByRole('alert')).toContainText(
        'different Applicant Numbers',
    );
    await expect(progress).toContainText('654321');
    await progress.getByRole('button', { name: 'Retake page 2' }).click();
    await expect(
        progress.getByRole('button', { name: 'Capture Page 2' }),
    ).toBeDisabled();
    await progress
        .getByLabel('Reason for retaking Page 2')
        .fill('Page 2 belongs to another examinee');
    await progress.getByRole('button', { name: 'Capture Page 2' }).click();
    await uploadPage();
    await expect(
        progress.getByRole('button', { name: 'Continue to sheet review' }),
    ).toBeVisible();
    expect(uploadedPages).toEqual([1, 2, 2]);
    const reload = page.waitForResponse(
        (response) =>
            response.url().endsWith(sessionPath) &&
            response.request().headers()['x-inertia'] === 'true',
    );
    await progress
        .getByRole('button', { name: 'Continue to sheet review' })
        .click();
    await reload;
    await expect(progress).toContainText('Ready for sheet review');
});
