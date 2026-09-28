import { test, expect } from '@playwright/test';

test('browser runtime reaches the public landing page', async ({ page }) => {
    await page.goto('/');

    await expect(page).toHaveTitle(/PlanOps|Laravel/);
});
