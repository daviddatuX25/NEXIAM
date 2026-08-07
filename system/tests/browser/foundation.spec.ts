import { expect, test } from '@playwright/test';

test('foundation page exposes the E0 React runtime status', async ({
    page,
}) => {
    await page.goto('/');

    await expect(
        page.getByRole('heading', { name: 'Foundation runtime is alive.' }),
    ).toBeVisible();
    await expect(page.getByText('E1-IN-PROGRESS')).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Check runtime health' }),
    ).toHaveAttribute('href', '/health');
});

test('health endpoint reports an operational response', async ({
    request,
    baseURL,
}) => {
    const response = await request.get(`${baseURL}/health`);

    expect(response.ok()).toBeTruthy();
    await expect(response.json()).resolves.toMatchObject({
        status: 'ok',
        database: 'ok',
    });
});
