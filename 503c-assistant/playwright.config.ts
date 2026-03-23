import { defineConfig, devices } from 'playwright/test';
import fs from 'node:fs';
import path from 'node:path';

function readDotEnvValue(key: string): string | undefined {
  const envPath = path.join(process.cwd(), '.env');
  if (!fs.existsSync(envPath)) return undefined;

  const raw = fs.readFileSync(envPath, 'utf8');
  const lines = raw.split(/\r?\n/);

  let out: string | undefined;
  for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    if (!trimmed.startsWith(`${key}=`)) continue;

    let v = trimmed.slice(key.length + 1);
    if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
      v = v.slice(1, -1);
    }
    out = v;
  }

  return out;
}

const baseURL = process.env.E2E_BASE_URL ?? readDotEnvValue('APP_URL') ?? 'http://127.0.0.1:8000';
const storageStatePath = '.playwright/.auth/admin.json';

function buildWebServerCommand(urlString: string): string {
  const u = new URL(urlString);
  const host = u.hostname || '127.0.0.1';

  let port = u.port;
  if (!port) {
    port = u.protocol === 'https:' ? '443' : '80';
  }

  return `php artisan serve --host=${host} --port=${port}`;
}

export default defineConfig({
  testDir: './tests/e2e',
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  outputDir: '.playwright/test-results',
  webServer: process.env.E2E_NO_WEB_SERVER
    ? undefined
    : {
        command: process.env.E2E_WEB_SERVER_COMMAND ?? buildWebServerCommand(baseURL),
        url: baseURL,
        reuseExistingServer: true,
        timeout: 120_000,
      },
  projects: [
    {
      name: 'setup',
      testMatch: /.*\.setup\.ts/,
      use: {
        ...devices['Desktop Chrome'],
      },
    },
    {
      name: 'chromium',
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: storageStatePath,
      },
    },
  ],
});
