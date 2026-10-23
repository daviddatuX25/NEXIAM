import { expect, test } from '@playwright/test';

test('public login has no authenticated Xiam dock', async ({ page }) => {
    await page.goto('/login');
    await expect(
        page.getByRole('button', { name: 'Open Xiam assistant' }),
    ).toHaveCount(0);
    await expect(page.getByLabel('Xiam assistant')).toHaveCount(0);
});

test('applicant login has no authenticated Xiam dock', async ({ page }) => {
    await page.goto('/applicant/login');
    await expect(
        page.getByRole('button', { name: 'Open Xiam assistant' }),
    ).toHaveCount(0);
    await expect(page.getByLabel('Xiam assistant')).toHaveCount(0);
});
