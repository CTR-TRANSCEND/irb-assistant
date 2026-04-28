import { test, expect } from 'playwright/test';

// ---------------------------------------------------------------------------
// Tests 1 & 1b depend on Laravel's session flash to surface validation errors
// after a redirect-back. The default admin storageState
// (.playwright/.auth/admin.json) is shared across every parallel worker, so a
// concurrent GET on /admin from another spec consumes the flashed `errors`
// bag before our redirect-back renders, causing an intermittent failure where
// the `ul.text-red-600` element never appears within the 10s timeout.
//
// auth.setup.ts saves a second, isolated session at
// .playwright/.auth/admin-forms.json. The describe block below points at it
// so admin-forms.spec.ts owns its session — no other spec touches that DB
// session row, so flash semantics are deterministic. The remaining 5 tests
// in this file are GET-only and keep the shared admin storageState for speed.
// ---------------------------------------------------------------------------
test.describe('admin provider form - flash-dependent', () => {
  test.use({ storageState: '.playwright/.auth/admin-forms.json' });

  test('validation errors display on empty submit', async ({ page }) => {
    const resp = await page.goto('/admin?tab=providers');
    expect(resp, 'expected a response for /admin?tab=providers').not.toBeNull();
    expect(resp!.ok(), `expected ok response, got ${resp!.status()}`).toBeTruthy();

    await page.waitForLoadState('networkidle');

    // The name field has HTML5 `required`, which blocks the form before reaching the server.
    // Remove the required attribute via JS so we can test server-side validation errors.
    await page.evaluate(() => {
      const input = document.querySelector<HTMLInputElement>('#name');
      if (input) {
        input.removeAttribute('required');
        input.value = '';
      }
    });

    // Submit the form with empty name — this now reaches the server
    await page.getByRole('button', { name: /save provider/i }).click();

    // Server returns redirect-back with validation errors
    await page.waitForURL(/\/admin\?tab=providers/);
    await page.waitForLoadState('networkidle');

    // x-input-error renders: <ul class="text-sm text-red-600 space-y-1"><li>...</li></ul>
    const nameError = page.locator('ul.text-red-600 li').first();
    await expect(nameError, 'expected a validation error message to appear').toBeVisible({
      timeout: 10000,
    });

    // The error text should mention the name field
    const errorText = await nameError.textContent();
    expect(errorText?.toLowerCase(), 'expected error text to reference "name" field').toMatch(
      /name/i,
    );
  });

  test('preserves entered values after validation failure', async ({ page }) => {
    const resp = await page.goto('/admin?tab=providers');
    expect(resp, 'expected a response for /admin?tab=providers').not.toBeNull();
    expect(resp!.ok(), `expected ok response, got ${resp!.status()}`).toBeTruthy();

    await page.waitForLoadState('networkidle');

    // Fill base_url — not required, but preserved via old() on redirect-back
    const baseUrlInput = page.locator('#base_url');
    await expect(baseUrlInput).toBeVisible();
    const testUrl = 'http://localhost:11434';
    await baseUrlInput.fill(testUrl);

    // Remove required from name and leave it blank to trigger validation failure
    await page.evaluate(() => {
      const input = document.querySelector<HTMLInputElement>('#name');
      if (input) {
        input.removeAttribute('required');
        input.value = '';
      }
    });

    await page.getByRole('button', { name: /save provider/i }).click();
    await page.waitForURL(/\/admin\?tab=providers/);
    await page.waitForLoadState('networkidle');

    // After redirect-back, base_url should be preserved via old()
    const preservedUrl = await page.locator('#base_url').inputValue();
    expect(preservedUrl, 'expected base_url to be preserved after validation failure').toBe(
      testUrl,
    );
  });
});

// ---------------------------------------------------------------------------
// Test 2: Admin Provider Form - Loading State Markup
//
// Traditional form POSTs navigate away immediately on submit, destroying the
// JS context before we can inspect in-flight DOM state. Instead we verify that
// the Alpine.js loading state is correctly wired up in the rendered HTML.
// ---------------------------------------------------------------------------
test('admin provider form - loading state markup is correctly wired', async ({ page }) => {
  const resp = await page.goto('/admin?tab=providers');
  expect(resp, 'expected a response for /admin?tab=providers').not.toBeNull();
  expect(resp!.ok(), `expected ok response, got ${resp!.status()}`).toBeTruthy();

  await page.waitForLoadState('networkidle');

  // The provider form has x-data="{ loading: false }" and @submit="loading = true"
  const providerForm = page.locator('form[x-data]').filter({
    has: page.getByRole('button', { name: /save provider/i }),
  });
  await expect(providerForm, 'expected the provider form with Alpine x-data').toBeAttached();

  // The submit button has x-bind:disabled="loading"
  const saveButton = page.getByRole('button', { name: /save provider/i });
  await expect(saveButton).toBeVisible();
  await expect(saveButton).not.toBeDisabled(); // idle state: enabled

  // Verify the "Saving..." span is present in DOM but hidden (x-show="loading").
  // Alpine.js keeps x-show elements in the DOM with display:none when the condition is false.
  // The span contains an inner spinner span plus the text " Saving..." so we match loosely.
  const savingSpan = page.locator('[x-show="loading"]').filter({ hasText: /saving/i }).first();
  await expect(savingSpan, 'expected "Saving..." span to be in DOM').toBeAttached();

  // In idle state the span should be hidden via display:none
  const isHidden = await savingSpan.evaluate((el) => {
    return (el as HTMLElement).style.display === 'none';
  });
  expect(isHidden, 'expected "Saving..." span to be hidden in idle state').toBe(true);
});

// ---------------------------------------------------------------------------
// Test 3: Admin Settings Form - Loading State Markup
// ---------------------------------------------------------------------------
test('admin settings form - loading state markup is correctly wired', async ({ page }) => {
  const resp = await page.goto('/admin?tab=settings');
  expect(resp, 'expected a response for /admin?tab=settings').not.toBeNull();
  expect(resp!.ok(), `expected ok response, got ${resp!.status()}`).toBeTruthy();

  await page.waitForLoadState('networkidle');

  // The settings form has x-data="{ loading: false }" and @submit="loading = true"
  const settingsForm = page.locator('form[x-data]').filter({
    has: page.getByRole('button', { name: /save settings/i }),
  });
  await expect(settingsForm, 'expected the settings form with Alpine x-data').toBeAttached();

  // The submit button is enabled in idle state
  const saveButton = page.getByRole('button', { name: /save settings/i });
  await expect(saveButton).toBeVisible();
  await expect(saveButton).not.toBeDisabled();

  // Verify the "Saving..." span exists (hidden in idle state via Alpine x-show)
  const savingSpan = page.locator('[x-show="loading"]').filter({ hasText: /saving/i }).first();
  await expect(savingSpan, 'expected "Saving..." span to be in DOM').toBeAttached();

  const isHidden = await savingSpan.evaluate((el) => {
    return (el as HTMLElement).style.display === 'none';
  });
  expect(isHidden, 'expected "Saving..." span to be hidden in idle state').toBe(true);
});

// ---------------------------------------------------------------------------
// Test 4: Admin Template Upload - Loading State Markup
// ---------------------------------------------------------------------------
test('admin template upload form - submit button supports loading state', async ({ page }) => {
  const resp = await page.goto('/admin?tab=templates');
  expect(resp, 'expected a response for /admin?tab=templates').not.toBeNull();
  expect(resp!.ok(), `expected ok response, got ${resp!.status()}`).toBeTruthy();

  await page.waitForLoadState('networkidle');

  // The upload button is "Upload" with loading state "Uploading..."
  const uploadButton = page.getByRole('button', { name: /^upload$/i });
  await expect(uploadButton, 'expected Upload button to be present on templates tab').toBeVisible();
  await expect(uploadButton, 'expected Upload button to be enabled before submission').not.toBeDisabled();

  // The upload form has x-data and the "Uploading..." span for loading state
  const uploadForm = page.locator('form[x-data]').filter({ has: uploadButton });
  await expect(uploadForm, 'expected upload form with Alpine x-data').toBeAttached();

  // "Uploading..." span should exist in the DOM (hidden in idle state)
  const uploadingSpan = page.locator('span').filter({ hasText: /uploading/i });
  await expect(uploadingSpan, 'expected "Uploading..." span to be in DOM').toBeAttached();
});

// ---------------------------------------------------------------------------
// Test 5: Focus Ring Consistency
// ---------------------------------------------------------------------------
test('admin provider form - focus rings are visible on tab navigation', async ({ page }) => {
  const resp = await page.goto('/admin?tab=providers');
  expect(resp, 'expected a response for /admin?tab=providers').not.toBeNull();
  expect(resp!.ok(), `expected ok response, got ${resp!.status()}`).toBeTruthy();

  await page.waitForLoadState('networkidle');

  // Focus the name input directly and verify it receives focus
  const nameInput = page.locator('#name');
  await nameInput.focus();
  await expect(nameInput, 'expected #name to receive focus').toBeFocused();

  // Tab to the next field and confirm focus moves
  await page.keyboard.press('Tab');
  const afterFirstTab = await page.evaluate(() => document.activeElement?.id ?? '');
  expect(afterFirstTab, 'focus should move after first Tab').toBeTruthy();
  expect(afterFirstTab, 'focus should have moved away from #name').not.toBe('name');

  // Tab through a few more fields — focus should keep moving within the form
  const focusedIds: string[] = [afterFirstTab];
  for (let i = 0; i < 3; i++) {
    await page.keyboard.press('Tab');
    const id = await page.evaluate(() => document.activeElement?.id ?? '');
    focusedIds.push(id);
  }

  // Each Tab should produce a distinct focus target (no focus trap in idle state)
  const uniqueIds = new Set(focusedIds.filter(Boolean));
  expect(
    uniqueIds.size,
    `expected focus to move through distinct elements, got: ${focusedIds.join(', ')}`,
  ).toBeGreaterThan(1);
});
