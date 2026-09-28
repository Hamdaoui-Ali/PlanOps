import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

import { loginAs } from './support/auth.js';

async function expectNoSeriousOrCriticalViolations(page) {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .analyze();

    expect(results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([]);
}

async function browserProjectPath(page) {
    await page.goto('/projects');
    const projectLink = page.getByRole('link', { name: 'Browser Collaboration', exact: true });
    await expect(projectLink).toBeVisible();

    return new URL(await projectLink.getAttribute('href'), page.url()).pathname;
}

test.describe('authenticated collaboration', () => {
    test('owner can inspect Team Work and use its keyboard-visible controls', async ({ page }) => {
        await loginAs(page, 'browser-owner@example.test');

        const projectPath = await browserProjectPath(page);
        await page.goto(projectPath);
        await page.getByRole('link', { name: 'Team Work', exact: true }).click();

        await expect(page).toHaveURL(new RegExp(`${projectPath}/team/work$`));
        await expect(page.getByRole('heading', { name: 'Team Work', exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Workload overview', exact: true })).toBeVisible();
        await expect(page.locator('[data-metric="unassigned"] strong')).toHaveText('1');
        await expect(page.locator('[data-metric="members"] strong')).toHaveText('3');

        const adminRow = page.getByRole('row', { name: /Browser Admin/ });
        await expect(adminRow).toContainText('Browser Admin');
        await expect(adminRow.locator('td').nth(0)).toHaveText('1');
        await expect(adminRow.locator('td').nth(1)).toHaveText('0');
        await expect(adminRow.locator('td').nth(2)).toHaveText('0');

        await page.getByRole('link', { name: 'Team', exact: true }).focus();
        await expect(page.locator(':focus')).toHaveText('Team');

        await expectNoSeriousOrCriticalViolations(page);
    });

    test('member receives a forbidden response for Team Work', async ({ page }) => {
        await loginAs(page, 'browser-member@example.test');

        const projectPath = await browserProjectPath(page);
        const response = await page.goto(`${projectPath}/team/work`);

        expect(response).not.toBeNull();
        expect(response.status()).toBe(403);
        await expect(page.getByRole('heading', { name: 'Team Work', exact: true })).toHaveCount(0);
    });
});
