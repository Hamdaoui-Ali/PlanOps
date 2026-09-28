import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const root = process.cwd();
const databasePath = path.resolve(root, 'storage/framework/testing/browser.sqlite');

export default async function globalSetup() {
    fs.mkdirSync(path.dirname(databasePath), { recursive: true });
    fs.rmSync(databasePath, { force: true });

    const env = {
        ...process.env,
        APP_ENV: 'testing',
        CACHE_STORE: 'array',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: databasePath,
        MAIL_MAILER: 'array',
        QUEUE_CONNECTION: 'sync',
        SESSION_DRIVER: 'array',
    };
    const php = process.env.PHP_BINARY || 'php';

    execFileSync(php, ['artisan', 'migrate:fresh', '--force', '--seed', '--seeder=BrowserSeeder', '--no-ansi'], {
        cwd: root,
        env,
        shell: process.platform === 'win32',
        stdio: 'inherit',
    });

    return async () => {
        fs.rmSync(databasePath, { force: true });
    };
}
