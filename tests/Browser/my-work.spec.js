import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

import { loginAs } from './support/auth.js';

async function expectNoSeriousOrCriticalViolations(page) {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .analyze();

    expect(results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([]);
}

test('assigned member can inspect My Work and filter it by label', async ({ page }) => {
    await loginAs(page, 'browser-admin@example.test');

    await page.getByRole('link', { name: 'My Work', exact: true }).click();
    await expect(page).toHaveURL(/\/my-work$/);
    await expect(page.getByRole('heading', { name: 'My Work', exact: true })).toBeVisible();

    const inProgress = page.getByRole('region', { name: 'In Progress' });
    const done = page.getByRole('region', { name: 'Done' });
    await expect(inProgress.getByRole('table')).toContainText('Review launch checklist');
    await expect(done.getByRole('table')).toContainText('Publish release notes');
    await expect(page.locator('table.my-work-table').getByText('Resolve release blocker', { exact: true })).toHaveCount(0);
    await expect(page.locator('table.my-work-table').getByText('Launch readiness', { exact: true })).toHaveCount(1);
    await expectNoSeriousOrCriticalViolations(page);

    await page.locator('#my-work-label').selectOption({ label: 'Launch readiness' });
    await page.getByRole('button', { name: 'Apply filters', exact: true }).click();

    await expect(page).toHaveURL(/\/my-work\?label=\d+$/);
    await expect(inProgress.getByRole('table')).toContainText('Review launch checklist');
    await expect(page.locator('table.my-work-table').getByText('Publish release notes', { exact: true })).toHaveCount(0);
    await expectNoSeriousOrCriticalViolations(page);
});
