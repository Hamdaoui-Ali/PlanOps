import { expect } from '@playwright/test';

export async function loginAs(page, email, password = 'password') {
    await page.goto('/login');
    await page.getByRole('textbox', { name: 'Email address' }).fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await page.getByRole('button', { name: 'Log In', exact: true }).click();
    await page.waitForURL('**/dashboard');
    await expect(page).toHaveURL(/\/dashboard$/);
}
