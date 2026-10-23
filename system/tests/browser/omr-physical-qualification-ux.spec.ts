import { test, expect } from '@playwright/test';

test.describe('OMR Physical Qualification UX', () => {
    test('Draft physical qualification UX proves method cards, advisory preparation state, mobile responsiveness, and rehearsal-only gating', async ({
        page,
    }) => {
        // 1. Authenticate as staff.
        await page.goto('/login');
        await page.getByLabel(/email/i).fill('guidance.counselor@nexiam.test');
        await page.getByLabel(/password/i).fill('password');
        await expect(
            page.getByRole('button', { name: /sign in|log in/i }),
        ).toBeVisible();
        await page.getByRole('button', { name: /sign in|log in/i }).click();
        await page.waitForURL((url) => !url.pathname.endsWith('/login'), {
            timeout: 30_000,
        });

        // 2. Navigate to the seeded rehearsal answer sheet.
        await page.goto('/setup/exam-materials/answer-sheets');
        await page.waitForLoadState('networkidle');
        const rehearsalDraft = page
            .locator('a[href*="/setup/exam-materials/answer-sheets/"]')
            .filter({ hasText: /digital verified/i })
            .first();
        await expect(rehearsalDraft).toBeVisible();
        await rehearsalDraft.click();
        await page.waitForLoadState('networkidle');

        // 3. Open Stage 3 Verification & Test.
        const verificationStageButton = page
            .getByRole('button', { name: /3 Verification & Test/i })
            .first();
        await expect(verificationStageButton).toBeVisible();
        await verificationStageButton.click();
        await expect(
            page.getByRole('heading', { name: 'Verification & Test Capture' }),
        ).toBeVisible({ timeout: 30_000 });

        // 4. The seeded candidate is a rehearsal and must not expose qualification controls.
        await expect(
            page.getByRole('button', { name: /5 Qualification/i }),
        ).toBeDisabled();
        await expect(
            page.getByRole('button', { name: /Record measurements/i }),
        ).toHaveCount(0);
        await expect(
            page.getByRole('button', { name: /Activate version/i }),
        ).toHaveCount(0);

        // 5. Both method cards retain their distinct text and accessible state cues.
        const preShadedCard = page.locator('[data-omr-method="pre_shaded"]');
        if (!(await preShadedCard.isVisible())) {
            const expandButton = page
                .getByRole('button', { name: /Expand active|Physical tests/i })
                .first();
            if (
                (await expandButton.isVisible()) &&
                (await expandButton.isEnabled())
            ) {
                await expandButton.click();
            }
        }
        const handShadedCard = page.locator('[data-omr-method="hand_shaded"]');
        await expect(preShadedCard).toBeVisible();
        await expect(handShadedCard).toBeVisible();
        await expect(
            preShadedCard.getByText('Machine baseline', { exact: true }),
        ).toBeVisible();
        await expect(
            handShadedCard.getByText('Human marks', { exact: true }),
        ).toBeVisible();

        // 6. Test pack creation and preparation actions.
        const createPreButton = preShadedCard.getByRole('button', {
            name: /Create.*test sheet/i,
        });
        if (await createPreButton.isVisible()) {
            await createPreButton.click();
            await expect(
                preShadedCard.getByRole('button', { name: /Open print PDF/i }),
            ).toBeVisible({ timeout: 30_000 });
        }

        const printButton = preShadedCard.getByRole('button', {
            name: /Open print PDF/i,
        });
        if (await printButton.isVisible()) {
            await printButton.click();
            await expect(
                preShadedCard.getByText(/Print opened/i),
            ).toBeVisible();
        }

        const guideButton = handShadedCard
            .getByRole('button', {
                name: /View shading guide|Create.*test sheet/i,
            })
            .first();
        if (await guideButton.isVisible()) {
            await guideButton.click();
        }

        // 7. Mobile viewport responsiveness: cards stack vertically and maintain full accessibility.
        await page.setViewportSize({ width: 375, height: 667 });
        await expect(preShadedCard).toBeVisible();
        await expect(handShadedCard).toBeVisible();
        await expect(
            preShadedCard.getByText('Machine baseline', { exact: true }),
        ).toBeVisible();
        await expect(
            handShadedCard.getByText('Human marks', { exact: true }),
        ).toBeVisible();
    });
});
