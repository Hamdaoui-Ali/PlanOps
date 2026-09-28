import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

async function expectNoSeriousOrCriticalViolations(page) {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .analyze();

    expect(results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([]);
}

test.describe('public accessibility', () => {
    test('landing page supports the skip link and has no serious axe violations', async ({ page }) => {
        await page.goto('/');

        await expect(page).toHaveTitle(/PlanOps/);
        await expect(page.getByRole('heading', { name: /Track the work/i })).toBeVisible();
        await expect(page.locator('#main-content')).toBeVisible();

        await page.keyboard.press('Tab');
        await expect(page.locator(':focus')).toHaveAttribute('href', '#main-content');
        await page.keyboard.press('Enter');

        await expectNoSeriousOrCriticalViolations(page);
    });

    test('login page exposes labelled controls and has no serious axe violations', async ({ page }) => {
        await page.goto('/login');

        await expect(page.getByRole('heading', { name: /Log in to PlanOps/i })).toBeVisible();
        await expect(page.getByLabel('Email address')).toBeVisible();
        await expect(page.getByRole('textbox', { name: 'Password' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Show password' })).toBeVisible();
        await expect(page.getByRole('button', { name: /Log In/i })).toBeVisible();

        await expectNoSeriousOrCriticalViolations(page);
    });
});
