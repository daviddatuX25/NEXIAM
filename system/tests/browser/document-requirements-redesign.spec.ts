import { expect, test, type Locator, type Page } from '@playwright/test';

const DESKTOP_VIEWPORT = { width: 1440, height: 900 };
const MOBILE_VIEWPORT = { width: 390, height: 844 };
const CHECKLIST_ATTEMPT_ID = 4536;

async function login(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 15_000 });
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
    const dimensions = await page.evaluate(() => ({
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: window.innerWidth,
    }));

    expect(dimensions.documentWidth).toBeLessThanOrEqual(dimensions.viewportWidth);
}

async function expectActionsOnOneRow(actionCell: Locator): Promise<void> {
    await expect.poll(() => actionCell.evaluate((element) => getComputedStyle(element).flexWrap)).toBe('nowrap');
    const buttons = actionCell.getByRole('button');
    const primary = actionCell.getByRole('link', { name: 'Edit', exact: true });
    const overflow = buttons.first();
    const [primaryBox, overflowBox] = await Promise.all([primary.boundingBox(), overflow.boundingBox()]);

    expect(primaryBox).not.toBeNull();
    expect(overflowBox).not.toBeNull();
    expect(Math.max(primaryBox!.y, overflowBox!.y)).toBeLessThan(
        Math.min(primaryBox!.y + primaryBox!.height, overflowBox!.y + overflowBox!.height),
    );
}

for (const { name, viewport } of [
    { name: 'desktop', viewport: DESKTOP_VIEWPORT },
    { name: 'mobile', viewport: MOBILE_VIEWPORT },
]) {
    test(`document requirement catalog journey at ${name} size`, async ({ page }) => {
        await page.setViewportSize(viewport);
        await login(page, 'registrar.checklist-writer@nexiam.test', 'ChecklistWriter!2026');
        await page.goto('/setup/requirements');

        const main = page.locator('#main-content');
        await expect(main.getByRole('heading', { name: 'Document requirements', exact: true })).toBeVisible();
        await expect(main).not.toContainText(/intake/i);
        await expect(main.getByText('Add document requirement', { exact: true }).first()).toBeVisible();
        await expect(main.getByPlaceholder('Search code or requirement label…')).toBeVisible();
        if (viewport.width === MOBILE_VIEWPORT.width) {
            await main.getByText('Filters', { exact: true }).click();
            for (const filter of ['Track', 'Kind', 'Mandatory', 'Status']) {
                await expect(main.getByLabel(filter).first()).toBeVisible();
            }
            await main.getByText('Filters', { exact: true }).click();
        } else {
            for (const filter of ['Track', 'Kind', 'Mandatory', 'Status']) {
                await expect(main.getByLabel(filter).last()).toBeVisible();
            }
        }
        await expect(main.getByLabel('Table view')).toBeVisible();
        await expect(main.getByLabel('Card view')).toBeVisible();
        if (viewport.width === MOBILE_VIEWPORT.width) {
            await expectNoHorizontalOverflow(page);
        }

        await main.getByLabel('Table view').click();
        const rows = main.locator('tbody tr');
        await expect(rows.first()).toBeVisible();
        const editLink = rows.first().getByRole('link', { name: 'Edit', exact: true });
        const editUrl = await editLink.getAttribute('href');
        expect(editUrl).toMatch(/\/setup\/requirements\/\d+\/edit$/);
        await expectActionsOnOneRow(rows.first().locator('[data-action-cell]'));

        await main.getByLabel('Card view').click();
        await expect(main.locator('[data-requirement-card][data-item-kind="document"]').first()).toBeVisible();
        await expect(main.locator('[data-requirement-card][data-item-kind="container"]').first()).toBeVisible();
        const cardActions = main.locator('[data-requirement-card-actions]').first();
        await expect(cardActions).toHaveClass(/flex-nowrap/);
        await expect(cardActions).toHaveClass(/whitespace-nowrap/);
        await expect.poll(() => cardActions.evaluate((element) => getComputedStyle(element).flexWrap)).toBe('nowrap');
        if (viewport.width === MOBILE_VIEWPORT.width) {
            await expectNoHorizontalOverflow(page);
        }

        await main.getByText('Add document requirement', { exact: true }).first().click();
        await expect(page).toHaveURL(/\/setup\/requirements\/create$/);
        if (viewport.width === MOBILE_VIEWPORT.width) {
            await expectNoHorizontalOverflow(page);
        }
        await expect(page.locator('[aria-label="Live requirement preview"] [data-requirement-card]')).toBeVisible();
        await expect(page.locator('#requirement-code')).toBeEditable();

        await page.goto(editUrl!);
        await expect(page).toHaveURL(/\/setup\/requirements\/\d+\/edit$/);
        await expect(page.getByRole('heading', { name: 'Edit document requirement' })).toBeVisible();
        await expect(page.locator('#requirement-code')).toHaveAttribute('readonly', '');
        await expect(page.getByText('Code cannot be changed after creation.', { exact: true })).toBeVisible();
        await expect(page.locator('[aria-label="Live requirement preview"] [data-requirement-card]')).toBeVisible();

        if (viewport.width === MOBILE_VIEWPORT.width) {
            await expectNoHorizontalOverflow(page);
        }
    });
}

test('document checklist dossier layout and progressive actions at desktop and mobile sizes', async ({ page }) => {
    await page.setViewportSize(DESKTOP_VIEWPORT);
    await login(page, 'registrar.checklist-writer@nexiam.test', 'ChecklistWriter!2026');
    await page.goto(`/admissions/${CHECKLIST_ATTEMPT_ID}`);

    const left = page.getByTestId('admission-dossier-left');
    const right = page.getByTestId('admission-dossier-right');
    await expect(left).toBeVisible();
    await expect(right).toBeVisible();
    const [leftBox, rightBox] = await Promise.all([left.boundingBox(), right.boundingBox()]);
    expect(leftBox).not.toBeNull();
    expect(rightBox).not.toBeNull();
    expect(Math.abs(leftBox!.width - rightBox!.width)).toBeLessThanOrEqual(2);
    expect(leftBox!.x + leftBox!.width).toBeLessThan(rightBox!.x);

    const dossierCards = ['Personal details', 'Program preferences', 'Timeline'];
    const cardBoxes: Array<{ x: number; y: number; width: number; height: number } | null> = [];
    for (const title of dossierCards) {
        const cardTitle = left.getByText(title, { exact: true });
        await expect(cardTitle).toBeVisible();
        cardBoxes.push(await cardTitle.boundingBox());
    }
    expect(cardBoxes.every((box) => box !== null)).toBe(true);
    for (let index = 1; index < cardBoxes.length; index += 1) {
        expect(cardBoxes[index]!.y).toBeGreaterThan(cardBoxes[index - 1]!.y);
    }

    const checklist = right.getByText('Document checklist', { exact: true });
    const checklistHeadingBox = await checklist.boundingBox();
    expect(checklistHeadingBox).not.toBeNull();
    expect(checklistHeadingBox!.x).toBeGreaterThan(leftBox!.x);
    const checklistCards = right.locator('[data-requirement-card]');
    await expect(checklistCards.first()).toBeVisible();
    const checklistCount = await checklistCards.count();
    expect(checklistCount).toBeGreaterThan(0);
    for (let index = 0; index < checklistCount; index += 1) {
        const card = checklistCards.nth(index);
        await expect(card.getByRole('button', { name: 'Mark complete', exact: true })).toBeVisible();
        const menuTrigger = card.getByRole('button', { name: /^More actions for / });
        await expect(menuTrigger).toBeVisible();
        const actionCell = card.locator('[data-action-cell]');
        await expect(actionCell).toHaveClass(/flex-nowrap/);
        await expect.poll(() => actionCell.evaluate((element) => getComputedStyle(element).flexWrap)).toBe('nowrap');
        const [primaryBox, overflowBox] = await Promise.all([
            card.getByRole('button', { name: 'Mark complete', exact: true }).boundingBox(),
            menuTrigger.boundingBox(),
        ]);
        expect(primaryBox).not.toBeNull();
        expect(overflowBox).not.toBeNull();
        expect(Math.max(primaryBox!.y, overflowBox!.y)).toBeLessThan(
            Math.min(primaryBox!.y + primaryBox!.height, overflowBox!.y + overflowBox!.height),
        );
        await expect(card.locator('textarea:visible, input[type="checkbox"]:visible')).toHaveCount(0);
    }

    const firstCard = checklistCards.first();
    await firstCard.getByRole('button', { name: /^More actions for / }).click();
    await expect(page.getByRole('menuitem', { name: 'Flag as deficient' })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Mark missing' })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Add/edit internal note' })).toBeVisible();
    await page.getByRole('menuitem', { name: 'Flag as deficient' }).click();
    await expect(firstCard.locator('textarea[id^="instruction-"]')).toBeVisible();
    await expect(firstCard.locator('textarea[id^="internal-"]')).toBeVisible();
    await expect(firstCard.getByRole('button', { name: 'Save deficient', exact: true })).toBeVisible();
    await firstCard.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(firstCard.locator('textarea:visible')).toHaveCount(0);

    const requestButton = right.getByRole('button', { name: 'Request document correction', exact: true });
    await expect(requestButton).toBeVisible();
    await requestButton.click();
    const requestDialog = page.getByRole('dialog');
    await expect(requestDialog).toBeVisible();
    const firstCheckbox = requestDialog.getByLabel('Include in document request').first();
    await expect(firstCheckbox).toBeVisible();
    await firstCheckbox.check();
    await expect(requestDialog.getByText('Applicant instruction for this request', { exact: true }).first()).toBeVisible();
    await expect(requestDialog.getByRole('button', { name: /^Review \d+ selected item/ })).toBeVisible();
    await requestDialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(requestDialog).toBeHidden();

    await page.setViewportSize(MOBILE_VIEWPORT);
    const mobileLeftBox = await left.boundingBox();
    const mobileRightBox = await right.boundingBox();
    expect(mobileLeftBox).not.toBeNull();
    expect(mobileRightBox).not.toBeNull();
    expect(mobileRightBox!.y).toBeGreaterThan(mobileLeftBox!.y);
    await expectNoHorizontalOverflow(page);
});
