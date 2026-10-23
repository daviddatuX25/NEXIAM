import { expect, test } from '@playwright/test';

test('authorized staff can open Dry Run from the Answer Sheet library', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel(/email/i).fill('guidance.counselor@nexiam.test');
    await page.getByLabel(/password/i).fill('password');
    await page.getByRole('button', { name: /sign in|log in/i }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));

    await page.goto('/setup/exam-materials/answer-sheets');
    await expect(page.getByRole('link', { name: 'Dry Run' })).toBeVisible();

    await page.goto('/setup');

    const dryRunLink = page.getByRole('link', { name: 'Dry Run' });
    await expect(dryRunLink).toBeVisible();
    await dryRunLink.click();

    await expect(
        page.getByRole('heading', { name: 'Answer-sheet templates' }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Dry Run' }).first(),
    ).toBeVisible();
});
