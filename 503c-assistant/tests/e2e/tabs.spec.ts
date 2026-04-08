import { test, expect } from 'playwright/test';

async function clickLinkAndAssertOk(
  page: import('playwright/test').Page,
  link: import('playwright/test').Locator,
  urlPattern: RegExp,
) {
  await link.click();
  await page.waitForURL(urlPattern, { timeout: 10000 });
}

test('post-login project tabs and admin tabs work', async ({ page }) => {
  // Projects index
  const resp = await page.goto('/projects');
  expect(resp, 'expected a response for /projects').not.toBeNull();
  expect(resp!.ok(), `expected ok response, got ${resp!.status()}`).toBeTruthy();

  await expect(page.getByRole('heading', { name: 'Projects', exact: true })).toBeVisible();

  // Create a new project via UI (avoids hardcoded UUIDs)
  const projectName = `E2E ${Date.now()}`;
  await page.locator('#name').fill(projectName);
  await page.getByRole('button', { name: 'Create' }).click();

  await page.waitForURL(/\/projects\/[0-9a-f-]{36}/);
  const projectUrl = page.url();
  const m = projectUrl.match(/\/projects\/([0-9a-f-]{36})/);
  expect(m, `expected project uuid in url: ${projectUrl}`).not.toBeNull();
  const projectUuid = m![1];

  // Verify all project workspace tabs
  const projectTabs: Array<{ name: string; tab: string; anchorText: string }> = [
    { name: 'Documents', tab: 'documents', anchorText: 'Upload documents' },
    { name: 'Review', tab: 'review', anchorText: 'Review AI suggestions' },
    { name: 'Questions', tab: 'questions', anchorText: 'Quick Fill' },
    { name: 'Export', tab: 'export', anchorText: 'Export Protocol' },
    { name: 'Activity', tab: 'activity', anchorText: 'Activity Log' },
  ];

  for (const t of projectTabs) {
    const link = page.getByRole('tab', { name: t.name, exact: true });
    await clickLinkAndAssertOk(page, link, new RegExp(`\\/projects\\/${projectUuid}\\?tab=${t.tab}`));
    await expect(page).toHaveURL(new RegExp(`\\/projects\\/${projectUuid}\\?tab=${t.tab}`));
    await expect(page.getByText(t.anchorText, { exact: false })).toBeVisible();
  }

  // Verify admin panel tabs (admin user)
  await page.goto('/admin?tab=audit');
  await expect(page.getByRole('heading', { name: 'System Management', exact: true })).toBeVisible();

  const adminTabs: Array<{ name: string; tab: string; anchorText: string }> = [
    { name: 'Users', tab: 'users', anchorText: 'Last login' },
    { name: 'LLM Providers', tab: 'providers', anchorText: 'Add / update provider' },
    { name: 'Templates', tab: 'templates', anchorText: 'Upload template' },
    { name: 'Settings', tab: 'settings', anchorText: 'System settings' },
    { name: 'Audit Log', tab: 'audit', anchorText: 'System Audit Log' },
  ];

  for (const t of adminTabs) {
    const link = page.getByRole('tab', { name: t.name, exact: true });
    await clickLinkAndAssertOk(page, link, new RegExp(`\\/admin\\?tab=${t.tab}`));
    await expect(page).toHaveURL(new RegExp(`\\/admin\\?tab=${t.tab}`));
    await expect(page.locator('div.p-6').getByText(t.anchorText, { exact: false }).first()).toBeVisible();
  }
});
