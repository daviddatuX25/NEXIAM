import { expect, test, type Page } from '@playwright/test';

async function signIn(page: Page, email = 'staff.dean@nexiam.test'): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 15_000 });
}

test.describe('Staff platform overhaul browser acceptance', () => {
    test('personnel directory has a single primary action, canonical role labels, and responsive controls', async ({ page }) => {
        await signIn(page);
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.goto('/staff/personnel');

        const table = page.locator('table');
        await expect(table).toBeVisible();
        await expect(table.getByRole('columnheader', { name: 'Access', exact: true })).toHaveCount(0);
        const ownRow = table.locator('tbody tr', { hasText: 'Staff Platform Dean CAS' });
        await expect(ownRow.getByRole('link', { name: 'Manage Profile' })).toBeVisible();
        await expect(table.locator('tbody tr').first().getByRole('button', { name: /actions/i })).toBeVisible();

        await expect(page.getByRole('columnheader', { name: /department|program/i })).toHaveCount(0);
        await expect(page.getByText(/Guidance.*(BSIT|BA-|program|department)/i)).toHaveCount(0);

        const officeFilter = page.locator('#personnel-office-desktop');
        await expect(page.locator('#personnel-department-desktop')).toBeVisible();
        await officeFilter.click();
        await page.locator('[data-slot="select-item"]').filter({ hasText: /Guidance/i }).click();
        await expect(page.locator('#personnel-department-desktop')).toHaveCount(0);
        await expect(page.locator('#personnel-program-desktop')).toHaveCount(0);
        await page.locator('#personnel-office-desktop').click();
        await page.locator('[data-slot="select-item"]').filter({ hasText: /All offices/i }).click();
        await expect(page.locator('#personnel-department-desktop')).toBeVisible();

        await page.setViewportSize({ width: 390, height: 844 });
        await page.reload();
        await expect(page.locator('body')).toHaveJSProperty('scrollWidth', 390);
        const ownCard = page.locator('[data-slot="bento-card"], article').filter({ hasText: 'Staff Platform Dean CAS' });
        await expect(ownCard.getByRole('link', { name: 'Manage Profile' })).toBeVisible();
        await expect(page.locator('[data-slot="bento-card"], article').first().getByRole('button', { name: /actions/i })).toBeVisible();
    });

    test('profile presents MFA status and opens authenticator enrollment', async ({ page }) => {
        await signIn(page, 'staff.proghead@nexiam.test');
        await page.goto('/staff/profile');

        await expect(page.getByText(/MFA Required|Two-Factor Authentication Active|MFA Optional for Your Role/).first()).toBeVisible();
        const setup = page.getByRole('button', { name: 'Set Up Two-Factor Authentication' });
        await expect(setup).toBeVisible();
        await setup.click();
        await expect(page.getByRole('dialog')).toBeVisible();
        await page.locator('#mfa-enrollment-password').fill('password');
        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByRole('img', { name: 'QR code for your new authenticator setup' })).toBeVisible({ timeout: 10_000 });
        await expect(page.getByText(/Six-digit authenticator code/i).first()).toBeVisible();
        await expect(page.getByRole('button', { name: /Copy setup key/i })).toBeVisible();
        await expect(page.locator('[data-pin-input-cell]')).toHaveCount(6);
    });

    test('inactivity warning offers extension presets and preferences expose workstation timeout to staff', async ({ page }) => {
        await signIn(page);
        await page.goto('/staff');
        await page.clock.install();
        await page.clock.fastForward(13 * 60 * 1000 + 1000);

        const warning = page.getByRole('dialog', { name: 'Session Inactivity Warning' });
        await expect(warning).toBeVisible();
        await expect(warning.getByRole('button', { name: /\+15 min/ })).toBeVisible();
        await expect(warning.getByRole('button', { name: /\+30 min/ })).toBeVisible();
        await expect(warning.getByRole('button', { name: /\+1 hour/ })).toBeVisible();

        await page.keyboard.press('Escape');
        const preferences = page.getByRole('button', { name: /accessibility and display preferences/i }).first();
        await preferences.click();
        await expect(page.getByText('Workstation Idle Timeout', { exact: true })).toBeVisible();
        await expect(page.getByText(/15 min — Standard/i)).toBeVisible();
    });
});
