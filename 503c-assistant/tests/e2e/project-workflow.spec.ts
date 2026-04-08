import { test, expect } from 'playwright/test';

// Shared helpers
async function createProject(
  page: import('playwright/test').Page,
  name: string,
): Promise<string> {
  await page.goto('/projects');
  await page.locator('#name').fill(name);
  await page.getByRole('button', { name: 'Create' }).click();
  await page.waitForURL(/\/projects\/[0-9a-f-]{36}/);

  const url = page.url();
  const match = url.match(/\/projects\/([0-9a-f-]{36})/);
  expect(match, `expected UUID in URL: ${url}`).not.toBeNull();
  return match![1];
}

async function navigateToTab(
  page: import('playwright/test').Page,
  tabName: string,
  projectUuid: string,
  tabParam: string,
): Promise<void> {
  const tab = page.getByRole('tab', { name: tabName, exact: true });
  await tab.click();
  await page.waitForURL(new RegExp(`/projects/${projectUuid}\\?tab=${tabParam}`));
}

// ---------------------------------------------------------------------------
// Test 1: Project creation and deletion
// ---------------------------------------------------------------------------
test('project creation and deletion removes project from index', async ({ page }) => {
  const projectName = `E2E Delete Test ${Date.now()}`;
  const projectUuid = await createProject(page, projectName);

  // Verify we are on the project show page
  await expect(page.getByRole('heading', { name: projectName, exact: true })).toBeVisible();

  // The Danger Zone with the delete button lives on the Activity tab
  await navigateToTab(page, 'Activity', projectUuid, 'activity');

  // Click the "Delete Project" danger button to open confirmation modal
  await page.getByRole('button', { name: /Delete Project/i }).click();

  // Wait for modal to be visible
  await expect(page.locator('#confirm_name')).toBeVisible();

  // Fill in confirmation: project name + password
  await page.locator('#confirm_name').fill(projectName);
  await page.locator('#password').fill('change-me');

  // Submit deletion
  await page.getByRole('button', { name: 'Delete project', exact: true }).click();

  // Should redirect to projects index
  await page.waitForURL(/\/projects$/);

  // Project must no longer appear in the list
  await expect(page.getByText(projectName, { exact: true })).not.toBeVisible();
});

// ---------------------------------------------------------------------------
// Test 2: Documents tab shows upload form
// ---------------------------------------------------------------------------
test('documents tab shows file input and upload button', async ({ page }) => {
  const projectName = `E2E Documents Tab ${Date.now()}`;
  const projectUuid = await createProject(page, projectName);

  await navigateToTab(page, 'Documents', projectUuid, 'documents');

  // Heading
  await expect(page.getByText('Upload documents', { exact: false })).toBeVisible();

  // File input
  const fileInput = page.locator('#documents');
  await expect(fileInput).toBeAttached();
  await expect(fileInput).toHaveAttribute('type', 'file');

  // Upload button
  const uploadButton = page.getByRole('button', { name: /Upload/i }).first();
  await expect(uploadButton).toBeVisible();
});

// ---------------------------------------------------------------------------
// Test 3: Export tab shows export section
// ---------------------------------------------------------------------------
test('export tab renders export section with export button', async ({ page }) => {
  const projectName = `E2E Export Tab ${Date.now()}`;
  const projectUuid = await createProject(page, projectName);

  await navigateToTab(page, 'Export', projectUuid, 'export');

  // Heading text
  await expect(page.getByText('Export Protocol', { exact: false })).toBeVisible();

  // Generate export button
  await expect(page.getByRole('button', { name: /Generate DOCX Export/i })).toBeVisible();
});

// ---------------------------------------------------------------------------
// Test 4: Questions tab shows Quick Fill heading and progress indicator
// ---------------------------------------------------------------------------
test('questions tab shows quick fill heading and field completion progress', async ({ page }) => {
  const projectName = `E2E Questions Tab ${Date.now()}`;
  const projectUuid = await createProject(page, projectName);

  await navigateToTab(page, 'Questions', projectUuid, 'questions');

  // "Quick Fill" heading must be present
  await expect(page.getByRole('heading', { name: 'Quick Fill', exact: true })).toBeVisible();

  // Progress indicator: circular SVG with percentage text
  // The progress text follows the pattern "X / Y" and "fields completed"
  await expect(page.getByText('fields completed', { exact: false })).toBeVisible();
});
