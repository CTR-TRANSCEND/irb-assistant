# Project Status (Goal, Specs, Accomplishments, Todos)

This file is the current, truthful state of the project: what the app is for, what it must do (spec), what is implemented, what is not, and what to do next.

## Goal

Build a local web app that helps users complete an HRP-503c (Human Research / Engagement Determination Application) from uploaded project documents by:

- extracting text from DOCX/PDF/TXT,
- producing suggested field values,
- attaching per-field evidence (citations back to source passages),
- allowing user edits/overrides with an audit trail,
- exporting a filled HRP-503c DOCX using an authoritative DOCX template.

## Specifications (Current, Implemented Scope)

Hard requirements implemented in this repository:

- Mandatory login (no anonymous use).
- Runs on local Linux/WSL2 without sudo (includes user-space MariaDB scripts).
- Admin panel for: users/roles, LLM providers (including a GLM 4.7 option), templates/mappings, and system settings.
- Supports local LLM endpoints and external LLM APIs (OpenAI-compatible `/chat/completions`), controlled by policy.
- Evidence view is traceable: each suggested field stores evidence rows linked to document chunks.
- DOCX export uses the active template and preserves formatting by filling Word content controls (SDTs).
- Audit log records key actions (admin changes, uploads, extraction, analysis, field edits, exports).
- Test suite exists and passes (`php artisan test`).

Security posture (implemented + documented):

- Input validation for uploads (type and size).
- External LLM usage can be globally disabled.
- Retention prune command exists (manual + cron docs).
- Remaining hardening items tracked in `SECURITY_CHECKLIST.md`.

## What Works Now (Functional)

### Local, no-sudo runtime
- Laravel app in `503c-assistant/`.
- Local MariaDB runs in user-space via `ops/db/start.sh` and `ops/db/stop.sh`.
- Frontend assets build via Vite (`npm run build`).

Environment notes:
- PHP LSP (intelephense) is not installed in this environment, so PHP diagnostics were validated via `php -l` + `php artisan test`.
- PHP `ZipArchive` is not available; DOCX operations rely on system `unzip`/`zip` commands.
- `pdftotext` was not detected; PDF text extraction uses `smalot/pdfparser`.

### Auth + roles
- Mandatory login (Breeze).
- Users have `role` (`admin|user`) + `is_active`.
- Disabled users are force-logged out by middleware.
- Admin user seeding via env (`ADMIN_*`).

### Projects + uploads + extraction
- Projects list + create + workspace tabs: Documents/Review/Questions/Export/Activity.
- Upload multiple documents (DOCX/PDF/TXT).
- Text extraction:
  - TXT: direct read
  - DOCX: `unzip -p` + parse `word/document.xml` (no ZipArchive required)
  - PDF: `smalot/pdfparser`
- Chunking into ~1400-char chunks stored in `document_chunks`.

### LLM provider + analysis (first pass)
- Admin UI can add providers (OpenAI-compatible style) and system can block external providers by policy.
- Provider types supported in code: `openai`, `openai_compat`, `lmstudio`, `ollama`, `glm47`.
- Admin UI supports "Test" to validate connectivity and records `last_test_*`.
- Analysis endpoint exists and writes:
  - `analysis_runs` with request/response payload
  - `project_field_values.suggested_value`
  - `field_evidence` (quote + chunk id)

Analysis behavior notes:
- Analysis now requires an active template and at least one mapping (`Admin > Templates`).
- Analysis only requests suggestions for mapped fields that are currently missing/unfilled.
- Analysis requires evidence for any non-empty suggestion (suggestions without citations are ignored).

### DOCX export
- Generates a DOCX by unzipping template, editing `word/document.xml` SDT controls, rezipping.
- Download endpoint exists.
- Export now **skips empty values** so it does not blank the template.
- Export fills mapped SDT controls for any discovered template parts: `document`, `endnotes`, `footnotes`, and any `headerN`/`footerN` parts present in the template.

### Tests
- `php artisan test` passes.
- Unit tests cover extraction, analysis (mocked LLM), export, and project initialization.
- Feature tests cover admin template mapping save and a user end-to-end flow (upload -> analyze -> export).

### Review UI
- Review tab now uses a two-pane layout: searchable field list + a detail panel with an evidence list.
- Questions tab is a guided list of missing fields (saves redirect back to the same tab).
- Evidence UI supports deep-linking to a specific evidence row and shows chunk context with quote highlight.

## Key Paths

- App: `503c-assistant/`
- Local DB scripts: `503c-assistant/ops/db/start.sh`, `503c-assistant/ops/db/stop.sh`
- Core tables: see migrations under `503c-assistant/database/migrations/2026_02_08_0116**_*.php`
- Core services:
  - Upload/extract: `app/Services/DocumentExtractionService.php`
  - Template scan/mapping seed: `app/Services/TemplateService.php`
  - LLM gateway: `app/Services/LlmChatService.php`
  - Analysis: `app/Services/ProjectAnalysisService.php`
  - Export: `app/Services/DocxExportService.php`
  - Settings: `app/Services/SettingsService.php`
  - Audit: `app/Services/AuditService.php`

- Admin templates/mapping:
  - Controller: `app/Http/Controllers/AdminTemplateController.php`
  - View: `resources/views/admin/template-controls.blade.php`

## How To Run

```bash
cd 503c-assistant
./ops/db/start.sh

cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed

npm install
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Admin bootstrap:
- `.env.example` includes `ADMIN_EMAIL=admin@example.com` and `ADMIN_PASSWORD=change-me`.
- If you change those values in `.env`, re-run:

```bash
php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder
```

## Major Known Gaps (Highest Priority)

1. **Field schema + mapping realism**
   - A curated field catalog exists (`hrp503c.*` keys) via `database/seeders/Hrp503cFieldDefinitionSeeder.php`.
   - However, there is no bundled, authoritative mapping set from the HRP-503c template controls to those curated fields; mapping is still admin-driven.

2. **Template drift robustness**
   - Mappings can be auto-copied by signature on upload and drift stats are shown.
   - There is no detailed “diff” report (per-control matches/misses) and no label-similarity mapping suggestions yet.

3. **Security hardening not complete**
   - No encryption-at-rest for uploads/derived text.
   - No malware scanning/quarantine.
   - Apache configs are provided as samples; production hardening is a deployment step.

4. **Evidence fidelity**
   - Evidence is stored and shown with context; however, chunks do not currently track precise source offsets/pages for DOCX and some PDFs.
   - Quote-in-chunk verification is not enforced (beyond chunk_id existence).

5. **DOCX control types**
   - Export fills SDT text; it does not implement richer control behaviors (checkbox/date/richtext semantics) beyond inserting text into `w:t`.

## Suggested Next Milestones

1. Provide a first-pass mapping pack for the included `resources/templates/HRP-503c.docx` (curated `hrp503c.*` keys -> specific control signatures).
2. Add optional mapping suggestions (label similarity + signature heuristics) to speed up admin mapping.
3. Increase evidence validation: enforce quote presence in the referenced chunk text; optionally store offsets.
4. Add project deletion/purge UI (respect retention + audit).
5. Optional: implement scheduled retention in-app (via Laravel scheduler) and document production process.

## Retention

Command exists:

```bash
php artisan irb:retention-prune --dry-run
php artisan irb:retention-prune
```

This prunes `project_documents` + `exports` older than the retention cutoff and deletes their stored files.

Deployment notes:

- `docs/deployment/apache-retention.md`

## Recent Changes (Most Recent)

- Templates: dynamic part discovery (headers/footers/endnotes/footnotes), dynamic mapping tabs, drift stats.
- Export: fills any mapped part discovered in the DOCX (not hardcoded to footer2).
- Fields: curated `hrp503c.*` catalog seeder added; project initialization now uses mapped fields to avoid noise.
- Analysis: includes `question_text` and requires evidence for non-empty suggestions.
- Evidence UI: deep-links + chunk context viewer with highlight.
- Deployment: Apache sample configs + retention cron docs; trusted proxies configured via `TRUSTED_PROXIES`.
- Tests: end-to-end feature test for upload -> analyze -> export.
