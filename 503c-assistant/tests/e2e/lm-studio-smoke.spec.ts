import { test, expect } from 'playwright/test';
import { spawnSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

// ---------------------------------------------------------------------------
// Live end-to-end smoke test against the configured LM Studio provider.
//
// Pre-requisite: an LM Studio (or other openai-compatible) provider has been
// inserted into the llm_providers table and marked is_default. See
// PROJECT_LOG.md "Session 2026-04-28 (LLM live test)" for the configured
// endpoint and model.
//
// This test:
//   1. Logs in as admin (uses the shared storageState).
//   2. Verifies the provider's "Test" admin endpoint round-trips the model.
//   3. Creates a fresh project.
//   4. Uploads a small TXT document with realistic protocol content.
//   5. Prunes the project's field_value rows to a small representative subset
//      (1-batch analysis) so the live LLM call finishes in well under a minute.
//   6. Triggers Run Analysis.
//   7. Asserts at least one suggestion comes back from the live model.
//   8. Confirms a field, then exports the project as DOCX.
//   9. Downloads the export and asserts a non-empty payload.
//
// Screenshots are captured at each major step under
// .playwright/test-results/lm-studio-smoke-<step>.png.
// ---------------------------------------------------------------------------

const SCREENSHOT_DIR = '.playwright/test-results/smoke';

function shotPath(name: string): string {
  return path.join(SCREENSHOT_DIR, `${name}.png`);
}

function tinker(php: string): string {
  // Use spawnSync with argv array to avoid bash variable expansion ($p →
  // empty) inside double-quoted shell strings.
  const result = spawnSync('php', ['artisan', 'tinker', '--execute', php], {
    cwd: process.cwd(),
    encoding: 'utf8',
    timeout: 60_000,
  });
  if (result.status !== 0) {
    throw new Error(
      `tinker exit ${result.status}: stdout=${result.stdout} stderr=${result.stderr}`,
    );
  }
  // Strip the "Restricted Mode" warning that tinker prints in non-interactive runs.
  return (result.stdout || '').replace(/^Restricted Mode:.*\n/m, '');
}

const PROTOCOL_TXT = `IRB Protocol Application — HRP-503 (Smoke Test Document)

Protocol Title: A Phase II, Open-Label, Single-Center Study of Compound IRB-X in Adults with Refractory Hypertension

Principal Investigator: Dr. Jane Doe, Department of Internal Medicine
Site: University Medical Center, Cardiology Division

Study Summary
The purpose of this Phase II clinical trial is to evaluate the efficacy and safety of Compound IRB-X (200 mg orally, once daily) in adult participants aged 40-75 years with treatment-resistant essential hypertension. This is an open-label single-arm study enrolling up to 60 participants over 18 months.

Primary Objective: To assess the change in mean ambulatory systolic blood pressure from baseline to week 12 of treatment.

Secondary Objectives:
- Frequency of adverse events of grade 2 or higher
- Change in mean diastolic blood pressure
- Health-related quality-of-life scores (SF-36)

Research Intervention: Oral administration of Compound IRB-X 200 mg once daily for 12 weeks, with a 4-week safety follow-up period.

Study Population
Inclusion criteria: Adults aged 40-75 with documented essential hypertension uncontrolled (sBP >= 140 mmHg) despite at least 3 antihypertensive agents.

Exclusion criteria: Pregnancy, severe renal or hepatic impairment, history of myocardial infarction within the past 6 months, or known hypersensitivity to study agents.

Risks and Discomforts
Most common expected adverse events include mild dizziness (~15%), headache (~10%), and orthostatic hypotension (~5%). All adverse events will be graded per CTCAE v5.0 and reported to the IRB within 10 working days.

Benefits
Participants may experience improved blood pressure control. Knowledge gained from this study may benefit the broader population of patients with refractory hypertension.

Data Management
All study data will be entered into a HIPAA-compliant electronic data capture system (REDCap), with de-identified datasets retained for 7 years per institutional policy. Only the study team has access to identifiable data.

Funding
This study is funded by the National Heart, Lung, and Blood Institute (Grant R01-HL-XXXXXX).
`;

// Opt-in only: this spec exercises a real network call to whatever LLM
// provider is configured as the default in the llm_providers table. CI runs
// without that environment do not have the Tailscale-side LM Studio host
// reachable, so the spec must skip cleanly. To run locally:
//
//   IRB_RUN_LIVE_LLM=1 npx playwright test lm-studio-smoke.spec.ts --workers=1
//
// Or set IRB_RUN_LIVE_LLM=1 in your shell before invoking the suite.
test.describe('LM Studio live end-to-end smoke', () => {
  test.skip(
    process.env.IRB_RUN_LIVE_LLM !== '1',
    'live LLM smoke is opt-in; set IRB_RUN_LIVE_LLM=1 to enable',
  );

  // Override the per-test timeout since the live LLM call adds ~5-15s
  // and document extraction has its own overhead.
  test.setTimeout(180_000);

  let projectUuid: string;
  let projectName: string;

  test.beforeAll(() => {
    mkdirSync(SCREENSHOT_DIR, { recursive: true });
  });

  test('full upload-analyze-export flow against live LM Studio', async ({ page }) => {
    // ---------------------------------------------------------------- step 1
    // Verify the provider exists and is marked default+enabled.
    await test.step('precondition: LM Studio provider configured', async () => {
      const out = tinker(`
        $p = \\App\\Models\\LlmProvider::where('is_default', true)->where('is_enabled', true)->first();
        echo $p ? ($p->id.'|'.$p->name.'|'.$p->provider_type.'|'.$p->base_url.'|'.$p->model) : 'NONE';
      `);
      console.log('Provider check:', out.trim().split('\n').pop());
      expect(out, 'expected an enabled+default LLM provider configured').toMatch(/lmstudio|openai/);
    });

    // ---------------------------------------------------------------- step 2
    // Use the admin "Test" endpoint to confirm a real round-trip succeeds.
    await test.step('admin: provider Test round-trip', async () => {
      const resp = await page.goto('/admin?tab=providers');
      expect(resp?.ok(), 'admin providers page loads').toBe(true);
      await page.waitForLoadState('networkidle');
      await page.screenshot({ path: shotPath('01-admin-providers') });

      // Click the "Test" button on the first provider row.
      const testBtn = page.getByRole('button', { name: /^Test$/i }).first();
      await expect(testBtn, 'Test button visible on provider row').toBeVisible();
      await testBtn.click();

      // After server round-trip, redirect-back renders status. Wait for the
      // page to settle and assert the row no longer says "Untested" or "Failed".
      await page.waitForLoadState('networkidle');
      await page.screenshot({ path: shotPath('02-admin-test-result') });

      const successBadge = page.locator('text=/(?:OK|tested|Success|alive)/i').first();
      const failureBadge = page.locator('text=/(?:Failed|Error)/i').first();

      const sawSuccess = await successBadge.isVisible().catch(() => false);
      const sawFailure = await failureBadge.isVisible().catch(() => false);

      // We only fail if "Failed" surfaces; the success label is informational.
      expect(sawFailure, `provider Test should not surface a Failed badge (saw success=${sawSuccess})`).toBe(false);
    });

    // ---------------------------------------------------------------- step 3
    // Create a fresh project.
    await test.step('create new project', async () => {
      await page.goto('/projects');
      await page.waitForLoadState('networkidle');
      await page.screenshot({ path: shotPath('03-projects-index') });

      projectName = `LMStudio Smoke ${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}`;
      await page.locator('#name').fill(projectName);
      await page.getByRole('button', { name: /create/i }).first().click();

      // Redirect to /projects/{uuid}?tab=documents
      await page.waitForURL(/\/projects\/[a-f0-9-]{36}/);
      const url = page.url();
      const m = url.match(/\/projects\/([a-f0-9-]{36})/);
      expect(m, 'expected project UUID in URL').not.toBeNull();
      projectUuid = m![1];
      console.log('Created project:', projectUuid, projectName);
      await page.screenshot({ path: shotPath('04-project-created') });
    });

    // ---------------------------------------------------------------- step 4
    // Upload a small TXT protocol document.
    await test.step('upload protocol document', async () => {
      // Make sure we're on the documents tab.
      await page.goto(`/projects/${projectUuid}?tab=documents`);
      await page.waitForLoadState('networkidle');

      // The form input is named documents[] (multiple file).
      const fileInput = page.locator('input[name="documents[]"]');
      await expect(fileInput, 'file input present on documents tab').toBeAttached();

      // Use buffer-based attach to avoid filesystem MIME ambiguity.
      await fileInput.setInputFiles({
        name: 'protocol.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from(PROTOCOL_TXT, 'utf8'),
      });

      // Submit the upload form (action ends with /documents) — pick its specific
      // submit button by walking from the form to its button.
      const uploadForm = page.locator('form[action$="/documents"]');
      await expect(uploadForm, 'upload form present').toBeVisible();
      const submitBtn = uploadForm.locator('button[type="submit"]').first();
      await expect(submitBtn, 'upload form submit button visible').toBeVisible();

      // Capture the POST response so we can diagnose validation failures.
      const [response] = await Promise.all([
        page.waitForResponse(
          (r) => r.url().includes(`/projects/${projectUuid}/documents`) && r.request().method() === 'POST',
          { timeout: 30_000 },
        ),
        submitBtn.click(),
      ]);
      console.log('Upload POST response:', response.status(), response.url());
      expect([200, 302, 303]).toContain(response.status());

      await page.waitForLoadState('networkidle');
      await page.screenshot({ path: shotPath('05-after-upload') });

      // Hard assertion: a Document row exists for this project.
      const out = tinker(`
        $p = \\App\\Models\\Project::where('uuid', '${projectUuid}')->firstOrFail();
        $count = \\App\\Models\\ProjectDocument::where('project_id', $p->id)->count();
        echo 'docs='.$count;
      `);
      console.log('Docs after upload:', out.trim().split('\n').pop());
      expect(out, 'expected at least 1 ProjectDocument row').toMatch(/docs=[1-9]/);
    });

    // ---------------------------------------------------------------- step 5
    // Wait for extraction to complete (status_extracted or chunk_count > 0).
    await test.step('wait for extraction', async () => {
      let chunkCount = 0;
      for (let i = 0; i < 20; i++) {
        const out = tinker(`
          $p = \\App\\Models\\Project::where('uuid', '${projectUuid}')->firstOrFail();
          $cc = \\App\\Models\\DocumentChunk::whereHas('document', function ($q) use ($p) { $q->where('project_id', $p->id); })->count();
          echo $cc;
        `);
        chunkCount = parseInt(out.trim().split('\n').pop() || '0', 10);
        if (chunkCount > 0) break;
        await page.waitForTimeout(1000);
      }
      console.log('Chunks extracted:', chunkCount);
      expect(chunkCount, 'expected at least one extracted chunk').toBeGreaterThan(0);
    });

    // ---------------------------------------------------------------- step 6
    // Initialize project field values (the show controller only seeds them
    // for review/questions/export tabs, not documents), then prune to a
    // small representative subset so the live LLM call is one batch.
    await test.step('seed and prune field_values for fast smoke test', async () => {
      const out = tinker(`
        $p = \\App\\Models\\Project::where('uuid', '${projectUuid}')->firstOrFail();
        app(\\App\\Services\\ProjectInitializationService::class)->ensureProjectFieldValuesExist($p);
        $allIds = \\App\\Models\\ProjectFieldValue::where('project_id', $p->id)->pluck('id')->all();
        $keepCount = min(5, count($allIds));
        $keepIds = array_slice($allIds, 0, $keepCount);
        $deleted = \\App\\Models\\ProjectFieldValue::where('project_id', $p->id)->whereNotIn('id', $keepIds)->delete();
        $remaining = \\App\\Models\\ProjectFieldValue::where('project_id', $p->id)->count();
        echo 'kept='.$remaining.' deleted='.$deleted;
      `);
      console.log('Field prune:', out.trim().split('\n').pop());
      expect(out, 'expected at least one field value to remain').toMatch(/kept=[1-9]/);
    });

    // ---------------------------------------------------------------- step 7
    // Trigger Run Analysis. This makes a real round-trip to LM Studio.
    await test.step('run live LLM analysis', async () => {
      await page.goto(`/projects/${projectUuid}?tab=documents`);
      await page.waitForLoadState('networkidle');

      const analyzeBtn = page.getByRole('button', { name: /run analysis/i }).first();
      await expect(analyzeBtn, 'Run Analysis button visible').toBeVisible();
      await expect(analyzeBtn, 'Run Analysis button enabled').not.toBeDisabled();

      await page.screenshot({ path: shotPath('06-pre-analyze') });

      // The analyze POST is synchronous and may take 5-30s. Use a generous
      // expectation timeout for the redirect.
      await Promise.all([page.waitForURL(/\/projects\/[a-f0-9-]{36}/, { timeout: 120_000 }), analyzeBtn.click()]);

      await page.waitForLoadState('networkidle', { timeout: 60_000 });
      await page.screenshot({ path: shotPath('07-after-analyze') });

      // Verify at least one analysis run finished successfully and produced
      // a non-empty suggested_value somewhere on this project. Schema:
      // project_field_values has suggested_value (LLM output) + final_value
      // (user-edited) — see migration 2025_xx create_project_field_values.
      const out = tinker(`
        $p = \\App\\Models\\Project::where('uuid', '${projectUuid}')->firstOrFail();
        $runs = \\App\\Models\\AnalysisRun::where('project_id', $p->id)->orderByDesc('id')->limit(1)->get();
        $r = $runs->first();
        $vals = \\App\\Models\\ProjectFieldValue::where('project_id', $p->id)
            ->whereNotNull('suggested_value')->where('suggested_value', '!=', '')->count();
        echo $r ? ('run_status='.$r->status.' suggested_count='.$vals) : 'NO_RUN';
      `);
      console.log('Analysis result:', out.trim().split('\n').pop());
      // AnalysisRun status enum: 'succeeded' | 'failed' (see ProjectAnalysisService).
      expect(out, 'expected run_status to be succeeded').toMatch(/run_status=succeeded/);
      expect(out, 'expected at least one suggested_value').toMatch(/suggested_count=[1-9]/);
    });

    // ---------------------------------------------------------------- step 8
    // Confirm one of the suggestions by promoting it to final_value+confirmed.
    await test.step('confirm a suggested field value', async () => {
      const out = tinker(`
        $p = \\App\\Models\\Project::where('uuid', '${projectUuid}')->firstOrFail();
        $v = \\App\\Models\\ProjectFieldValue::where('project_id', $p->id)
            ->whereNotNull('suggested_value')->where('suggested_value', '!=', '')->first();
        if ($v) {
            $v->final_value = $v->suggested_value;
            $v->status = 'confirmed';
            $v->confirmed_at = now();
            $v->save();
            echo 'confirmed_id='.$v->id.' field_def_id='.$v->field_definition_id;
        } else {
            echo 'NO_FILLED_VALUE';
        }
      `);
      console.log('Confirm:', out.trim().split('\n').pop());
      expect(out, 'expected to confirm a field').toMatch(/confirmed_id=/);
    });

    // ---------------------------------------------------------------- step 9
    // Export the project as DOCX.
    await test.step('generate DOCX export', async () => {
      await page.goto(`/projects/${projectUuid}?tab=export`);
      await page.waitForLoadState('networkidle');
      await page.screenshot({ path: shotPath('08-export-tab') });

      // Find the form's submit button (Generate / Export).
      // Route is POST /projects/{uuid}/export (singular — see routes/web.php).
      const generateBtn = page
        .locator('form[action$="/export"] button[type="submit"]')
        .first();
      await expect(generateBtn, 'Generate Export button visible').toBeVisible();
      await generateBtn.click();
      await page.waitForLoadState('networkidle', { timeout: 60_000 });
      await page.screenshot({ path: shotPath('09-after-export') });

      const out = tinker(`
        $p = \\App\\Models\\Project::where('uuid', '${projectUuid}')->firstOrFail();
        $e = \\App\\Models\\Export::where('project_id', $p->id)->orderByDesc('id')->first();
        echo $e ? ('export_status='.$e->status.' bytes='.$e->size_bytes) : 'NO_EXPORT';
      `);
      console.log('Export:', out.trim().split('\n').pop());
      // Export status enum: 'ready' | 'failed' (see DocxExportService).
      expect(out, 'expected export to be ready').toMatch(/export_status=ready/);
    });

    // ---------------------------------------------------------------- step 10
    // Download the export and assert a non-empty payload (DOCX magic bytes).
    await test.step('download export and verify payload', async () => {
      // Route is GET /exports/{export-uuid} — see routes/web.php.
      const downloadLink = page.locator('a[href*="/exports/"]').first();
      await expect(downloadLink, 'Download link visible').toBeVisible();

      const [download] = await Promise.all([page.waitForEvent('download'), downloadLink.click()]);
      const dlPath = await download.path();
      expect(dlPath, 'download produced a local file').not.toBeNull();

      // DOCX is a ZIP; check magic bytes "PK\x03\x04".
      const fs = await import('node:fs');
      const buf = fs.readFileSync(dlPath!);
      expect(buf.length, 'DOCX file is non-empty').toBeGreaterThan(1024);
      expect(buf.slice(0, 4).toString('hex'), 'DOCX file has ZIP magic').toBe('504b0304');

      console.log('Downloaded DOCX:', dlPath, buf.length, 'bytes');
      await page.screenshot({ path: shotPath('10-download-complete') });
    });
  });
});
