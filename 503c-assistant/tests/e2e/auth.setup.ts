import { test, expect } from 'playwright/test';
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

const email = process.env.E2E_EMAIL ?? readDotEnvValue('ADMIN_EMAIL') ?? 'admin@example.com';
const password = process.env.E2E_PASSWORD ?? readDotEnvValue('ADMIN_PASSWORD') ?? 'change-me';
const storageStatePath = '.playwright/.auth/admin.json';

test('login and save storageState', async ({ page }) => {
  await page.goto('/login');

  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.getByRole('button', { name: /log in/i }).click();

  await expect(page).toHaveURL(/\/projects/);
  await expect(page.getByRole('heading', { name: 'Projects', exact: true }).first()).toBeVisible();

  fs.mkdirSync(path.dirname(storageStatePath), { recursive: true });
  await page.context().storageState({ path: storageStatePath });
});
