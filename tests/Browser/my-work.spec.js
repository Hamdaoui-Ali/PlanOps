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
    await loginAs(page, 'browser-member@example.test');

    await page.getByRole('link', { name: 'My Work', exact: true }).click();
    await expect(page).toHaveURL(/\/my-work$/);
    await expect(page.getByRole('heading', { name: 'My Work', exact: true })).toBeVisible();

    const notStarted = page.getByRole('region', { name: 'Not Started' });
    await expect(notStarted.getByRole('table')).toContainText('Validate member handoff');
    await expect(page.locator('table.my-work-table').getByText('Review launch checklist', { exact: true })).toHaveCount(0);
    await expect(page.locator('table.my-work-table').getByText('Publish release notes', { exact: true })).toHaveCount(0);
    await expect(page.locator('table.my-work-table').getByText('Resolve release blocker', { exact: true })).toHaveCount(0);
    await expect(page.locator('table.my-work-table').getByText('Launch readiness', { exact: true })).toHaveCount(1);
    await expectNoSeriousOrCriticalViolations(page);

    await page.locator('#my-work-label').selectOption({ label: 'Launch readiness' });
    await page.getByRole('button', { name: 'Apply filters', exact: true }).click();

    await expect(page).toHaveURL(/\/my-work\?.*label=\d+/);
    await expect(notStarted.getByRole('table')).toContainText('Validate member handoff');
    await expect(page.locator('table.my-work-table').getByText('Review launch checklist', { exact: true })).toHaveCount(0);
    await expectNoSeriousOrCriticalViolations(page);
});

test('owner can reassign project work to an active member', async ({ page }) => {
    await loginAs(page, 'browser-owner@example.test');

    await page.goto('/projects');
    const projectLink = page.getByRole('link', { name: 'Browser Collaboration', exact: true });
    await expect(projectLink).toBeVisible();
    const projectPath = new URL(await projectLink.getAttribute('href'), page.url()).pathname;

    await page.goto(projectPath);
    await page.getByRole('link', { name: 'Review launch checklist', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Review launch checklist', exact: true })).toBeVisible();

    const assignee = page.locator('#task-assignee');
    await expect(assignee.locator('option:checked')).toHaveText('Browser Admin');
    await assignee.selectOption({ label: 'Browser Member' });
    await page.getByRole('button', { name: 'Save assignee', exact: true }).click();

    await expect(page.locator('.planops-flash[role="status"]')).toContainText('Task assignment updated.');
    await expect(assignee.locator('option:checked')).toHaveText('Browser Member');
    await expectNoSeriousOrCriticalViolations(page);
});
