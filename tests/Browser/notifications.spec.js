import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

import { loginAs } from './support/auth.js';

async function expectNoSeriousOrCriticalViolations(page) {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .analyze();

    expect(results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([]);
}

test.describe('authenticated notifications', () => {
    test('invitee can review and accept a pending invitation from notifications', async ({ page }) => {
        await loginAs(page, 'browser-invitee@example.test');

        await page.locator('a.planops-notifications-link').click();
        await expect(page).toHaveURL(/\/notifications$/);
        await expect(page.getByRole('heading', { name: 'Notifications', exact: true })).toBeVisible();

        const invitation = page.locator('[data-notification-id]').first();
        await expect(invitation.getByRole('heading', { name: 'Browser Owner invited you to collaborate', exact: true })).toBeVisible();
        await expect(invitation).toContainText('Browser Invitations');
        await expect(invitation.getByRole('button', { name: 'Accept invitation', exact: true })).toBeVisible();
        await expect(invitation.getByRole('button', { name: 'Decline', exact: true })).toBeVisible();
        await expectNoSeriousOrCriticalViolations(page);

        await invitation.getByRole('button', { name: 'Accept invitation', exact: true }).click();

        await expect(page).toHaveURL(/\/projects$/);
        await expect(page.getByRole('link', { name: 'Browser Invitations', exact: true })).toBeVisible();
    });

    test('owner can mark an unread notification as read', async ({ page }) => {
        await loginAs(page, 'browser-owner@example.test');

        const notificationsLink = page.locator('a.planops-notifications-link');
        await expect(notificationsLink.locator('#notification-count-badge')).toHaveText('1');
        await notificationsLink.click();
        await expect(page).toHaveURL(/\/notifications$/);
        await expect(page.getByText('You have 1 unread notification.', { exact: true })).toBeVisible();

        const notification = page.locator('[data-notification-id]').first();
        await expect(notification.getByRole('heading', { name: 'Release review needs your attention.', exact: true })).toBeVisible();
        await expectNoSeriousOrCriticalViolations(page);

        await notification.getByRole('button', { name: 'Mark as read', exact: true }).click();

        await expect(page).toHaveURL(/\/notifications$/);
        await expect(page.getByText('You have 0 unread notifications.', { exact: true })).toBeVisible();
        await expect(notification.getByText('Read', { exact: true })).toBeVisible();
        await expect(notification.getByRole('button', { name: 'Mark as read', exact: true })).toHaveCount(0);
    });
});
