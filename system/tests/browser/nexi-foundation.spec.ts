import { expect, test } from '@playwright/test';

test('public login has no authenticated Nexi dock', async ({ page }) => {
  await page.goto('/login');
  await expect(page.getByRole('button', { name: 'Open Nexi assistant' })).toHaveCount(0);
  await expect(page.getByLabel('Nexi assistant')).toHaveCount(0);
});

test('applicant login has no authenticated Nexi dock', async ({ page }) => {
  await page.goto('/applicant/login');
  await expect(page.getByRole('button', { name: 'Open Nexi assistant' })).toHaveCount(0);
  await expect(page.getByLabel('Nexi assistant')).toHaveCount(0);
});
