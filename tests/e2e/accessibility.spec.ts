import { test, expect } from 'playwright/test';
import AxeBuilder from '@axe-core/playwright';

type AxeViolation = {
  id: string;
  impact?: string | null;
  description: string;
  nodes: unknown[];
};

function summarizeViolations(violations: AxeViolation[]): string {
  return violations
    .map(
      (v) =>
        `[${v.impact ?? 'unknown'}] ${v.id}: ${v.description} — ${v.nodes.length} node(s) affected`,
    )
    .join('\n');
}

test.describe('accessibility audit', () => {
  test('login page passes axe audit', async ({ page }) => {
    await page.goto('/login');
    await page.waitForLoadState('networkidle');

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();

    expect(
      results.violations,
      `Axe violations on /login:\n${summarizeViolations(results.violations)}`,
    ).toEqual([]);
  });

  test('projects page passes axe audit', async ({ page }) => {
    await page.goto('/projects');
    await page.waitForLoadState('networkidle');

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();

    expect(
      results.violations,
      `Axe violations on /projects:\n${summarizeViolations(results.violations)}`,
    ).toEqual([]);
  });

  test('admin page passes axe audit', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();

    expect(
      results.violations,
      `Axe violations on /admin:\n${summarizeViolations(results.violations)}`,
    ).toEqual([]);
  });
});
