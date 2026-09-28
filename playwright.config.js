import { defineConfig } from '@playwright/test';
import path from 'node:path';

const browserDatabase = path.resolve('storage/framework/testing/browser.sqlite');

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    workers: 1,
    timeout: 30_000,
    globalSetup: './tests/Browser/global-setup.js',
    reporter: [
        ['list'],
        ['html', { outputFolder: 'storage/framework/testing/playwright-report', open: 'never' }],
    ],
    use: {
        baseURL: 'http://127.0.0.1:8001',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    webServer: {
        command: 'php artisan serve --no-reload --host=127.0.0.1 --port=8001',
        url: 'http://127.0.0.1:8001',
        timeout: 120_000,
        reuseExistingServer: false,
        env: {
            ...process.env,
            APP_ENV: 'testing',
            CACHE_STORE: 'array',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: browserDatabase,
            MAIL_MAILER: 'array',
            QUEUE_CONNECTION: 'sync',
            SESSION_DRIVER: 'cookie',
        },
    },
});
