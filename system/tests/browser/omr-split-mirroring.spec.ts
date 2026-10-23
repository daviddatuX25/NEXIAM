import { test, expect } from '@playwright/test';

test.describe('OMR Desktop Split & Mobile Deck Mirroring on Both Hosts', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        if (page.url().includes('/login')) {
            await page.getByLabel(/email/i).fill('guidance.counselor@nexiam.test');
            await page.locator('input#password').fill('password');
            await page.getByRole('button', { name: /sign in|log in/i }).click();
            await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 30_000 });
        }
    });

    test('Production host renders desktop side-by-side capture and queue', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto('/scoring/sessions/1/omr');
        await page.locator('[data-omr-workbench]').waitFor({ state: 'visible', timeout: 15_000 });

        const captureBox = await page.locator('[data-omr-stage="capture"]').boundingBox();
        const reviewBox = await page.locator('[data-omr-queue-selection]').boundingBox();

        expect(captureBox).not.toBeNull();
        expect(reviewBox).not.toBeNull();

        // Capture is on the left, the scan queue is on the right.
        expect(captureBox!.x).toBeLessThan(reviewBox!.x);
        // Both start near the top
        expect(Math.abs(captureBox!.y - reviewBox!.y)).toBeLessThan(350);
    });

    test('Diagnostic host (/setup/exam-materials/omr-test) renders desktop side-by-side split with camera deck', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto('/setup/exam-materials/omr-test');
        
        const startBtn = page.getByRole('button', { name: 'Start diagnostic run', exact: true });
        if (await startBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
            await startBtn.click();
        }
        await page.locator('[data-omr-workbench]').waitFor({ state: 'visible', timeout: 15_000 });

        const captureBox = await page.locator('[data-omr-stage="capture"]').boundingBox();
        const reviewBox = await page.locator('[data-omr-queue-selection]').boundingBox();

        expect(captureBox).not.toBeNull();
        expect(reviewBox).not.toBeNull();

        // Capture is on the left, the scan queue is on the right.
        expect(captureBox!.x).toBeLessThan(reviewBox!.x);
        expect(Math.abs(captureBox!.y - reviewBox!.y)).toBeLessThan(350);

        // Has camera deck and permanent diagnostic banner
        await expect(page.locator('[data-omr-camera-deck]')).toBeVisible();
        await expect(page.locator('[data-omr-diagnostic-banner]').first()).toBeVisible();
        await expect(page.locator('[data-omr-diagnostic-banner]').first()).toContainText(/no production writes/i);
    });

    test('Mobile viewport (375px) has zero horizontal overflow on both hosts', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 667 });

        // Check production
        await page.goto('/scoring/sessions/1/omr');
        await page.locator('[data-omr-workbench]').waitFor({ state: 'visible', timeout: 15_000 });
        const prodOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
        expect(prodOverflow).toBe(false);

        // Check diagnostic
        await page.goto('/setup/exam-materials/omr-test');
        const startBtn = page.getByRole('button', { name: 'Start diagnostic run', exact: true });
        if (await startBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
            await startBtn.click();
        }
        await page.locator('[data-omr-workbench]').waitFor({ state: 'visible', timeout: 15_000 });
        const diagOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
        expect(diagOverflow).toBe(false);
    });
});
