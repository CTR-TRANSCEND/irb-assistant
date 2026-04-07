import { test, expect } from 'playwright/test';

// ---------------------------------------------------------------------------
// Test 1: Project Creation Workflow
// ---------------------------------------------------------------------------
test('project creation workflow', async ({ page }) => {
  const resp = await page.goto('/projects');
  expect(resp, 'expected a response for /projects').not.toBeNull();
  expect(resp!.ok(), `expected ok response, got ${resp!.status()}`).toBeTruthy();

  await expect(page.getByRole('heading', { name: 'Projects', exact: true }).first()).toBeVisible();

  // Fill in the project name and submit
  const projectName = 'E2E Test Study';
  await page.locator('#name').fill(projectName);
  await page.getByRole('button', { name: 'Create' }).click();

  // Verify redirect to the project show page (UUID in URL)
  await page.waitForURL(/\/projects\/[0-9a-f-]{36}/);
  const projectUrl = page.url();
  const match = projectUrl.match(/\/projects\/([0-9a-f-]{36})/);
  expect(match, `expected project uuid in url: ${projectUrl}`).not.toBeNull();

  // Verify project name appears as heading on the show page
  await expect(page.getByRole('heading', { name: projectName, exact: true })).toBeVisible();
});

// ---------------------------------------------------------------------------
// Test 2: Admin Panel Navigation - All 6 Tabs
// ---------------------------------------------------------------------------
test('admin panel navigation - all 6 tabs render', async ({ page }) => {
  const resp = await page.goto('/admin');
  expect(resp, 'expected a response for /admin').not.toBeNull();
  expect(resp!.ok(), `expected ok response, got ${resp!.status()}`).toBeTruthy();

  // Verify tablist accessibility role exists
  await expect(page.getByRole('tablist')).toBeVisible();

  // All 6 tab labels that must be present
  const expectedTabLabels = [
    'Users',
    'LLM Providers',
    'Templates',
    'Settings',
    'Observability',
    'Audit Log',
  ];

  for (const label of expectedTabLabels) {
    await expect(
      page.getByRole('tab', { name: label }),
      `expected tab "${label}" to be visible`,
    ).toBeVisible();
  }

  // Click each tab and verify it loads content without error
  const adminTabs: Array<{ name: string; tab: string; anchorText: string }> = [
    { name: 'Users', tab: 'users', anchorText: 'Last Login' },
    { name: 'LLM Providers', tab: 'providers', anchorText: 'Add / Update Provider' },
    { name: 'Templates', tab: 'templates', anchorText: 'Upload Template' },
    { name: 'Settings', tab: 'settings', anchorText: 'System Settings' },
    { name: 'Audit Log', tab: 'audit', anchorText: 'Audit log' },
  ];

  for (const t of adminTabs) {
    const tabLink = page.getByRole('tab', { name: t.name });
    await tabLink.click();
    await page.waitForURL(new RegExp(`/admin\\?tab=${t.tab}`));
    const tabResp = await page.request.get(page.url());
    expect(tabResp.ok(), `expected ok response on tab ${t.tab}`).toBeTruthy();
  }

  // Verify Observability tab shows "Provider Usage" section
  const obsTab = page.getByRole('tab', { name: 'Observability' });
  await obsTab.click();
  await page.waitForURL(/\/admin\?tab=observability/);
  await expect(page.getByText('Provider Usage', { exact: false })).toBeVisible();
});

// ---------------------------------------------------------------------------
// Test 3: Admin Run Detail (if runs exist on Observability tab)
// ---------------------------------------------------------------------------
test('admin run detail page renders when runs exist', async ({ page }) => {
  const resp = await page.goto('/admin?tab=observability');
  expect(resp, 'expected a response for /admin?tab=observability').not.toBeNull();
  expect(resp!.ok(), `expected ok response, got ${resp!.status()}`).toBeTruthy();

  // Look for any "Detail" links (run detail links)
  const detailLinks = page.getByRole('link', { name: /detail/i });
  const count = await detailLinks.count();

  if (count === 0) {
    // No runs exist — pass the test gracefully
    console.log('No analysis runs present; skipping run detail assertions.');
    return;
  }

  // Click the first available run detail link
  const firstLink = detailLinks.first();
  await firstLink.click();

  // Verify the detail page URL pattern
  await page.waitForURL(/\/admin\/runs\/[0-9a-f-]{36}/);
  const detailResp = await page.request.get(page.url());
  expect(detailResp.ok(), 'expected ok response on run detail page').toBeTruthy();

  // Verify the page shows run UUID (mono font text in identity card)
  const uuidPattern = /[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i;
  const uuidLocator = page.locator('h3.font-mono').first();
  await expect(uuidLocator).toBeVisible();
  const uuidText = await uuidLocator.innerText();
  expect(uuidText, 'expected uuid text in run identity card').toMatch(uuidPattern);

  // Verify run status badge is present
  const statusBadge = page.locator('[role="status"]');
  await expect(statusBadge).toBeVisible();
});

// ---------------------------------------------------------------------------
// Test 4: Profile Management
// ---------------------------------------------------------------------------
test('profile management - view, update name, and verify persistence', async ({ page }) => {
  const resp = await page.goto('/profile');
  expect(resp, 'expected a response for /profile').not.toBeNull();
  expect(resp!.ok(), `expected ok response, got ${resp!.status()}`).toBeTruthy();

  // Verify profile form renders with name and email inputs
  const nameInput = page.locator('#name').first();
  const emailInput = page.locator('#email').first();

  await expect(nameInput).toBeVisible();
  await expect(emailInput).toBeVisible();

  // Verify the email field shows the logged-in admin email
  const emailValue = await emailInput.inputValue();
  expect(emailValue, 'expected admin email to be pre-filled').toBe('admin@example.com');

  // Read the current name so we can restore it after the test
  const originalName = await nameInput.inputValue();

  // Update the name to a new value
  const updatedName = `Admin Updated ${Date.now()}`;
  await nameInput.fill(updatedName);

  // Submit the profile update form (Save button)
  await page.getByRole('button', { name: 'Save' }).first().click();

  // Wait for the page to reload after the PATCH and verify the name persists
  await page.waitForURL(/\/profile/);
  const nameInputAfterSave = page.locator('#name').first();
  await expect(nameInputAfterSave).toBeVisible();
  const savedName = await nameInputAfterSave.inputValue();
  expect(savedName, 'expected name to be updated after save').toBe(updatedName);

  // Restore the original name to avoid side effects on other tests
  await nameInputAfterSave.fill(originalName);
  await page.getByRole('button', { name: 'Save' }).first().click();
  await page.waitForURL(/\/profile/);
});

// ---------------------------------------------------------------------------
// Test 5: Accessibility Checks
// ---------------------------------------------------------------------------
test('accessibility - breadcrumb, tab roles, and skip-to-content link', async ({ page }) => {
  // --- Projects page: breadcrumb navigation ---
  const projectsResp = await page.goto('/projects');
  expect(projectsResp, 'expected a response for /projects').not.toBeNull();
  expect(projectsResp!.ok(), `expected ok response, got ${projectsResp!.status()}`).toBeTruthy();

  // Breadcrumb with aria-label="Breadcrumb" must exist
  const breadcrumb = page.locator('nav[aria-label="Breadcrumb"]');
  await expect(breadcrumb, 'expected breadcrumb nav on projects page').toBeVisible();

  // --- Admin page: tablist and tab ARIA roles ---
  const adminResp = await page.goto('/admin');
  expect(adminResp, 'expected a response for /admin').not.toBeNull();
  expect(adminResp!.ok(), `expected ok response, got ${adminResp!.status()}`).toBeTruthy();

  // role="tablist" must be present
  const tablist = page.getByRole('tablist');
  await expect(tablist, 'expected role="tablist" on admin page').toBeVisible();

  // At least one role="tab" must be present and visible
  const tabs = page.getByRole('tab');
  const tabCount = await tabs.count();
  expect(tabCount, 'expected at least 6 tab elements').toBeGreaterThanOrEqual(6);

  // --- Main layout: skip-to-content link ---
  // The skip link is sr-only by default; verify it exists in the DOM
  const skipLink = page.locator('a[href="#main-content"]');
  await expect(skipLink, 'expected skip-to-content link in DOM').toHaveAttribute(
    'href',
    '#main-content',
  );

  // Verify the main content landmark target exists
  const mainContent = page.locator('#main-content');
  await expect(mainContent, 'expected #main-content element on the page').toBeAttached();
});
