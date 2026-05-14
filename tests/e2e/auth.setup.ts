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
const adminFormsStatePath = '.playwright/.auth/admin-forms.json';

async function loginAndSave(page: import('playwright/test').Page, savePath: string): Promise<void> {
  await page.goto('/login');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.getByRole('button', { name: /log in/i }).click();
  await expect(page).toHaveURL(/\/projects/);
  await expect(page.getByRole('heading', { name: 'Projects', exact: true }).first()).toBeVisible();
  fs.mkdirSync(path.dirname(savePath), { recursive: true });
  await page.context().storageState({ path: savePath });
}

test('login and save storageState', async ({ page }) => {
  await loginAndSave(page, storageStatePath);
});

// admin-forms.spec.ts (Tests 1 and 1b) depends on Laravel's session-flash for
// validation errors after a redirect-back. Sharing the main admin storageState
// with other parallel workers caused intermittent failures: a concurrent GET
// on the shared session would consume the flashed `errors` bag before our
// redirect-back rendered. Save a SECOND, isolated session that no other spec
// uses, so admin-forms.spec.ts has private flash semantics. Login throttle
// (5/min on /login) tolerates this one extra login per Playwright run.
test('login and save admin-forms storageState (isolated session)', async ({ page, context }) => {
  await context.clearCookies();
  await loginAndSave(page, adminFormsStatePath);
});
