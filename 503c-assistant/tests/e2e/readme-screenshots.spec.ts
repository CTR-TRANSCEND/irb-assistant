import { test, expect } from 'playwright/test';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

function tinker(php: string): string {
  const result = spawnSync('php', ['artisan', 'tinker', '--execute', php], {
    cwd: process.cwd(),
    encoding: 'utf8',
    timeout: 60_000,
  });
  if (result.status !== 0) {
    throw new Error(`tinker exit ${result.status}: ${result.stderr}`);
  }
  return (result.stdout || '').replace(/^Restricted Mode:.*\n/m, '');
}

// ---------------------------------------------------------------------------
// Captures the 5 README marketing screenshots at 1280x800 to match the
// previously-tracked set under 503c-assistant/screenshots/. Opt-in via
// IRB_CAPTURE_SCREENSHOTS=1 so the spec does not run in CI; CI does not
// have the LM Studio data, the demo project, or the desire to overwrite
// repository assets on every run.
//
//   IRB_CAPTURE_SCREENSHOTS=1 npx playwright test \
//       readme-screenshots.spec.ts --workers=1
//
// Pre-conditions enforced by the test:
//   - admin storageState exists (created by the regular auth.setup.ts
//     project; running this spec inherits it via the chromium project
//     dependency).
//   - One project named "Phase II Hypertension Trial Protocol" exists
//     with at least 1 uploaded document and at least one suggested
//     field value (used for the documents and review tab shots).
//
// Spec output:
//   503c-assistant/screenshots/01-login.png
//   503c-assistant/screenshots/02-projects-dashboard.png
//   503c-assistant/screenshots/03-project-documents.png
//   503c-assistant/screenshots/04-project-review.png
//   503c-assistant/screenshots/05-admin-panel.png
// ---------------------------------------------------------------------------

const VIEWPORT = { width: 1280, height: 800 };
const OUTDIR = 'screenshots';
const DEMO_PROJECT_NAME = 'Phase II Hypertension Trial Protocol';

function shotPath(name: string): string {
  return path.join(OUTDIR, name);
}

test.describe('README screenshots', () => {
  test.skip(
    process.env.IRB_CAPTURE_SCREENSHOTS !== '1',
    'opt-in: set IRB_CAPTURE_SCREENSHOTS=1 to overwrite tracked README assets',
  );

  test.use({ viewport: VIEWPORT });

  test.beforeAll(() => {
    fs.mkdirSync(OUTDIR, { recursive: true });
  });

  // -------------------------------------------------------------------
  // 01 — Login page (logged-out state, no auth cookies). Force empty
  // storageState so the context cannot inherit the admin session and
  // get redirected away from /login by the auth.guest middleware.
  // -------------------------------------------------------------------
  test('01-login', async ({ browser }) => {
    const context = await browser.newContext({
      viewport: VIEWPORT,
      storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();
    await page.goto('/login');
    await page.waitForLoadState('networkidle');
    // Sanity: the page must NOT have redirected away from /login.
    expect(page.url()).toMatch(/\/login(?:\?.*)?$/);
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await page.screenshot({ path: shotPath('01-login.png') });
    await context.close();
  });

  // -------------------------------------------------------------------
  // 02 — Projects dashboard. Inherits admin storageState so we land on
  // the index with at least one tracked project.
  // -------------------------------------------------------------------
  test('02-projects-dashboard', async ({ page }) => {
    await page.goto('/projects');
    await page.waitForLoadState('networkidle');
    await expect(page.getByRole('heading', { name: 'Projects', exact: true }).first()).toBeVisible();
    await page.screenshot({ path: shotPath('02-projects-dashboard.png') });
  });

  // -------------------------------------------------------------------
  // 03 — Project Documents tab. Picks the demo project by name.
  // -------------------------------------------------------------------
  test('03-project-documents', async ({ page }) => {
    await page.goto('/projects');
    await page.waitForLoadState('networkidle');

    const link = page.getByRole('link', { name: new RegExp(DEMO_PROJECT_NAME, 'i') }).first();
    await expect(link, `expected demo project "${DEMO_PROJECT_NAME}" on dashboard`).toBeVisible();
    await link.click();
    await page.waitForURL(/\/projects\/[a-f0-9-]{36}/);
    await page.waitForLoadState('networkidle');

    // Make sure we land on documents tab (the default tab).
    const tabDocs = page.locator('a#tab-documents');
    if (await tabDocs.isVisible()) {
      const isActive = await tabDocs.getAttribute('aria-selected');
      if (isActive !== 'true') {
        await tabDocs.click();
        await page.waitForLoadState('networkidle');
      }
    }
    await page.screenshot({ path: shotPath('03-project-documents.png') });
  });

  // -------------------------------------------------------------------
  // 04 — Project Review tab — the marketing money shot. Shows
  // AI-suggested values in the field list and an editor on the right.
  // -------------------------------------------------------------------
  test('04-project-review', async ({ page }) => {
    // Look up the demo project + its first suggested field-value id so we
    // can deep-link to ?tab=review&fv=<id> and ensure the right-hand
    // editor displays a populated LLM suggestion (not a missing field).
    const out = tinker(`
      $p = \\App\\Models\\Project::where('name', '${DEMO_PROJECT_NAME}')->firstOrFail();
      $fv = \\App\\Models\\ProjectFieldValue::where('project_id', $p->id)
          ->where('status', 'suggested')->orderBy('id')->first();
      echo $p->uuid.'|'.($fv ? $fv->id : '0');
    `);
    const last = out.trim().split('\n').pop() || '';
    const [uuid, fvId] = last.split('|');
    expect(uuid, 'expected to resolve demo project uuid').toMatch(/^[a-f0-9-]{36}$/);
    expect(fvId, 'expected at least one suggested field on demo project').not.toBe('0');

    await page.goto(`/projects/${uuid}?tab=review&fv=${fvId}`);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: shotPath('04-project-review.png') });
  });

  // -------------------------------------------------------------------
  // 05 — Admin panel — Providers tab so the configured LM Studio entry
  // is visible in the listing.
  // -------------------------------------------------------------------
  test('05-admin-panel', async ({ page }) => {
    await page.goto('/admin?tab=providers');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('text=/Configured Providers/i').first()).toBeVisible();
    await page.screenshot({ path: shotPath('05-admin-panel.png') });
  });
});
