import { expect, test, type Page } from '@playwright/test';

test.describe('Restored Legacy Surfaces Verification', () => {
    test('registrar admin can view staff operations dashboard and reports hub', async ({ page }) => {
        page.on('console', msg => console.log('BROWSER CONSOLE:', msg.type(), msg.text()));
        page.on('pageerror', err => console.log('PAGE ERROR:', err.message));

        // Login as Registrar Admin
        await page.goto('/login');
        await page.locator('#email').fill('registrar.admin@nexiam.test');
        await page.locator('#password').fill('password');
        await page.locator('button[type="submit"]').click();
        await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 15_000 });
        await page.goto('/staff');
        await expect(page.locator('#main-content h1')).toContainText('Operations Dashboard');
        await expect(page.getByText('Total Inflow')).toBeVisible();
        await expect(page.getByText('In Pipeline')).toBeVisible();
        await expect(page.getByText('Finalized')).toBeVisible();
        await expect(page.getByText('Dismissed')).toBeVisible();
        await expect(page.locator('canvas').first()).toBeVisible({ timeout: 10_000 });

        await page.screenshot({ path: '../docs/restored-staff-dashboard.png', fullPage: true });

        // 2. Verify Reports Hub (/reports)
        await page.goto('/reports');
        await expect(page.locator('#main-content h1')).toContainText('Reports & Analytics Hub');
        await expect(page.getByText('Applications Summary Census')).toBeVisible();
        await expect(page.getByText('Program Demand & Preferences')).toBeVisible();
        await expect(page.locator('canvas').first()).toBeVisible({ timeout: 10_000 });

        await page.screenshot({ path: '../docs/restored-reports-hub.png', fullPage: true });

        // 3. Verify Audit Log Explorer (/staff/logs)
        await page.goto('/staff/logs');
        await expect(page.locator('#main-content h1')).toContainText('Audit Log Explorer');
        await expect(page.getByText('Timestamp')).toBeVisible();
        await expect(page.getByText('Event Key')).toBeVisible();

        await page.screenshot({ path: '../docs/restored-audit-logs.png', fullPage: true });
    });

    test('guidance counselor can view live exam monitoring telemetry', async ({ page }) => {
        // Login as Guidance Counselor
        await page.goto('/login');
        await page.locator('#email').fill('guidance.counselor@nexiam.test');
        await page.locator('#password').fill('password');
        await page.locator('button[type="submit"]').click();
        await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 15_000 });

        // 4. Verify Exam Monitoring Telemetry (/exams/monitoring)
        await page.goto('/exams/monitoring');
        await expect(page.locator('#main-content h1')).toContainText('Exam Monitoring');
        await expect(page.getByText('Auto-refreshing (15s)')).toBeVisible();

        await page.screenshot({ path: '../docs/restored-exam-monitoring.png', fullPage: true });
    });
});
