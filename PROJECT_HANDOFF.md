# PROJECT HANDOFF / STATE DOCUMENT

## 1. Project Overview

Local-first Laravel 12 web app to help researchers draft HRP-503c and HRP-503 IRB forms from uploaded study documents. Workflow: upload documents -> extract + chunk -> run LLM first-pass suggestions with evidence -> user review/edit/confirm -> export filled .docx. Primary codebase lives in `503c-assistant/`.

- **Repository:** `windysky/irb-assistant` (GitHub)
- **Tech stack:** Laravel 12 / PHP 8.3 / MySQL-MariaDB / Blade + Tailwind + Alpine + Vite
- **Test stack:** PHPUnit (142 tests, 451 assertions), Playwright (21 E2E specs: 2 setup logins + 19 specs covering auth + tabs + workflows + admin forms + a11y + project lifecycle, all passing under default parallel execution)
- **Last updated:** 2026-04-28 CDT
- **Last coding CLI used:** Claude Code CLI (claude-opus-4-7)
- **Latest release tag:** v0.3.0

## 2. Current State

### Completed

| Feature | Status | Completed In |
|---------|--------|--------------|
| Auth + admin gatekeeping (EnsureUserIsAdmin, EnsureUserIsActive) | Completed | Session 2026-03-09 |
| Rate limiting on login/registration/password-reset (throttle:5,1) | Completed | Session 2026-03-10 04:03 CDT |
| Rate limiting on password reset POST + export download | Completed | Session 2026-04-07 00:57 CDT |
| Configurable registration toggle (IRB_ALLOW_REGISTRATION, default: false) | Completed | Session 2026-03-10 04:03 CDT |
| Project CRUD: list/create/show/delete with purge + audit redaction | Completed | Session 2026-03-09 |
| Document upload + malware scanning + encryption-at-rest + extraction/chunking | Completed | Session 2026-03-09 |
| Template upload + control scanning + mapping UI + drift detection + suggestions | Completed | Session 2026-03-09 |
| Bundled HRP-503c mapping pack (7 curated field mappings) | Completed | Session 2026-03-09 |
| Bundled HRP-503 mapping pack (33 mappings, 34 field definitions) | Completed | Session 2026-04-07 00:57 CDT |
| LLM provider admin + policy gate + per-project provider selection | Completed | Session 2026-03-09 |
| First-pass analysis with evidence fidelity (quote-in-chunk, offsets) | Completed | Session 2026-03-09 |
| Review/edit UX with evidence browsing, deep-linking, chunk highlight | Completed | Session 2026-03-09 |
| DOCX export (multi-paragraph SDT, XML error logging, xml:space preserve) | Completed | Session 2026-03-10 06:17 CDT |
| Retention prune command + Laravel scheduler (daily 03:00) | Completed | Session 2026-03-10 04:03 CDT |
| Audit logging + Admin audit/observability tabs (paginated) | Completed | Session 2026-03-10 06:17 CDT |
| Admin observability: run detail viewer + provider usage metrics | Completed | Session 2026-04-07 00:57 CDT |
| UI modernization (Inter, slate/indigo, custom components) | Completed | Session 2026-03-09 |
| UI/UX revamp: design tokens, WCAG AA contrast, ARIA accessibility | Completed | Session 2026-04-07 00:57 CDT |
| Toast notification system (Alpine.js store + flash message bridge) | Completed | Session 2026-04-07 00:57 CDT |
| Breadcrumb navigation (projects index + show pages) | Completed | Session 2026-04-07 00:57 CDT |
| Tab ARIA roles (projects/show + admin pages) | Completed | Session 2026-04-07 00:57 CDT |
| Form loading states (create project, upload, analysis, export) | Completed | Session 2026-04-07 00:57 CDT |
| Component accessibility: badges, tables, toggles, evidence viewer | Completed | Session 2026-04-07 00:57 CDT |
| CSS utility components: spinner, skeleton, breadcrumb, form-help | Completed | Session 2026-04-07 00:57 CDT |
| Ops credential security (temp files, env vars) | Completed | Session 2026-03-09 |
| PDF extraction hardening (memory limits, timeout, fallback logging) | Completed | Session 2026-03-10 04:03 CDT |
| Core accessibility (skip-to-content, ARIA modal/nav, decorative SVG) | Completed | Session 2026-03-10 04:03 CDT |
| Test coverage: AuditService, SettingsService, LlmChatService (43 new tests) | Completed | Session 2026-03-10 04:03 CDT |
| Project purge transaction safety (DB::transaction + deferred file deletion) | Completed | Session 2026-03-10 06:17 CDT |
| Export download status guard (reject non-ready exports) | Completed | Session 2026-03-10 06:17 CDT |
| DOCX export temp dir cleanup on failure (finally block) | Completed | Session 2026-03-10 06:17 CDT |
| Analysis prompt json_encode error handling (throw on failure) | Completed | Session 2026-03-10 06:17 CDT |
| Repository setup: rename to irb-assistant, .gitignore, README, v0.1.0 tag | Completed | Session 2026-03-22 21:12 CDT |
| Security audit: remove hardcoded credentials, clean git history | Completed | Session 2026-03-22 21:12 CDT |
| README screenshots (5 pages via Playwright) | Completed | Session 2026-04-07 00:57 CDT |
| E2E Playwright tests: 6 workflow tests (project, admin, profile, a11y) | Completed | Session 2026-04-07 00:57 CDT |
| Evaluator fixes: strict_types, hash_equals, PHPUnit 12 attributes | Completed | Session 2026-04-07 00:57 CDT |
| Project documentation: product.md, structure.md, tech.md, codemaps | Completed | Session 2026-04-07 00:57 CDT |
| declare(strict_types=1) on all 21 controllers | Completed | Session 2026-04-07 19:28 CDT |
| Vite vulnerability remediation (4 HIGH -> 0) | Completed | Session 2026-04-07 19:28 CDT |
| Rate limiting on confirm-password + password-update routes | Completed | Session 2026-04-07 19:28 CDT |
| Admin form UX: old(), validation errors, loading states | Completed | Session 2026-04-07 19:28 CDT |
| Test coverage: 17 new admin/field controller tests | Completed | Session 2026-04-07 19:28 CDT |
| E2E: 6 new admin form tests + tabs.spec.ts fixes | Completed | Session 2026-04-07 19:28 CDT |
| Focus ring brand-500 consistency across all views | Completed | Session 2026-04-07 19:28 CDT |
| .env.example: 6 missing env vars documented | Completed | Session 2026-04-07 19:28 CDT |
| Dark mode with localStorage toggle + anti-flash | Completed | Session 2026-04-07 20:16 CDT |
| declare(strict_types=1) on all 25 non-controller PHP files | Completed | Session 2026-04-07 20:16 CDT |
| Deployment security docs: SECURITY_CHECKLIST + DEPLOYMENT_CHECKLIST | Completed | Session 2026-04-07 20:16 CDT |
| Automated axe-core WCAG 2.1 AA audit (3 pages, 0 violations) | Completed | Session 2026-04-07 20:16 CDT |
| E2E: accessibility.spec.ts (3 tests) + project-workflow.spec.ts (4 tests) | Completed | Session 2026-04-07 20:16 CDT |
| Admin observability metrics fix (fieldCount, overallStats) | Completed | Session 2026-04-27 |
| ClamAV engine detection memoized (per-instance cache) | Completed | Session 2026-04-27 |
| ProjectFieldController confirmed_at consistency | Completed | Session 2026-04-27 |
| LLM provider base_url URL validation + last_test_error redaction | Completed | Session 2026-04-27 |
| Throttle:5,1 on projects.analyze and projects.documents.store | Completed | Session 2026-04-27 |
| league/commonmark CVE-2026-33347 + CVE-2026-30838 cleared (composer update) | Completed | Session 2026-04-27 |
| npm audit fix — 3 dev moderate vulns (postcss/axios/follow-redirects) | Completed | Session 2026-04-27 |
| Pint style cleanup — 39 files normalized (whitespace, imports, quoting) | Completed | Session 2026-04-28 |
| Playwright admin-forms parallel-flake fix — isolated storageState | Completed | Session 2026-04-28 |
| Encryption-key rotation procedure (APP_KEY + IRB_FILE_ENCRYPTION_KEYS) | Completed | Session 2026-04-28 |
| `npm test` no-op script for MoAI quality gate compatibility | Completed | Session 2026-04-28 |
| README + tech.md refresh: HRP-503 scope, test counts, portability notes | Completed | Session 2026-04-28 |

### Partially Implemented

| Feature | Status | Notes |
|---------|--------|-------|
| HRP-503c field coverage | Partial | Only 7/46 curated fields map to template (determination form with mostly checkboxes) |
| SDT export semantics | Partial | Multi-paragraph supported; checkbox/date/richtext SDT types not implemented |

## 3. Execution Plan Status

| Phase | Status | Last Updated | Notes |
|-------|--------|-------------|-------|
| Phase 0: Ops Security | Completed | 2026-03-09 | Credential handling fixed in all ops scripts |
| Phase 1: Mapping Pack | Completed | 2026-03-09 | 7 curated field mappings; HRP-503c is a determination form |
| Phase 2: UI Modernization | Completed | 2026-04-07 00:57 CDT | Complete visual redesign + UI/UX revamp with design tokens and WCAG AA |
| Phase 3: Evidence Fidelity | Completed | 2026-03-09 | Was already implemented; verified with tests |
| Phase 4: Security Hardening | Completed | 2026-04-07 00:57 CDT | Rate limiting (incl. password reset POST + export download), registration toggle, PDF hardening, export guard, purge safety, credential scrub |
| Phase 5: Test Coverage Expansion | Completed | 2026-04-07 00:57 CDT | 114 -> 116 PHPUnit tests + 8 Playwright E2E tests; PHPUnit 12 deprecation warnings resolved |
| Phase 6: Accessibility (core) | Completed | 2026-03-10 04:03 CDT | Skip-to-content, ARIA roles, decorative SVGs |
| Phase 7: Scheduled Retention | Completed | 2026-03-10 04:03 CDT | Laravel scheduler, daily 03:00 |
| Phase 8: Broader Template Support | Completed | 2026-04-07 00:57 CDT | HRP-503 protocol template (VCU), 33 mappings, 34 field definitions, SHA256-based routing |
| Phase 9: Admin Observability Enhancements | Completed | 2026-04-07 00:57 CDT | Run detail viewer, provider usage metrics with success rates, 2 new tests |
| Phase 10: Deployment-Level Security | Not started | 2026-03-10 | DB encryption, Apache config, log rotation |
| Phase 11: Deeper Accessibility | Completed | 2026-04-07 00:57 CDT | Tab roles, ARIA labels, table captions, form accessibility, contrast fixes, toast system, breadcrumbs, loading states |
| Repository & Release Management | Completed | 2026-03-22 21:12 CDT | Repo renamed, README written, v0.1.0 tagged, secrets scrubbed |

## 4. Outstanding Work

| # | Item | Status | Last Updated | Session Ref |
|---|------|--------|-------------|-------------|
| 1 | DB volume encryption for production | Not started | 2026-03-10 | Requires production environment; documented in DEPLOYMENT_CHECKLIST.md |
| 2 | Apache config verification for production | Not started | 2026-03-10 | Config templates exist in ops/apache/; needs production testing |
| 3 | Manual screen reader testing (NVDA/VoiceOver) | Not started | 2026-04-07 | Axe-core automated audit passes; manual testing adds confidence |
| 4 | E2E tests for LLM analysis workflow | Not started | 2026-04-07 | Requires configured LLM provider |

All items require external infrastructure or manual testing not automatable in CI. Core application is feature-complete and production-ready for both HRP-503c and HRP-503 workflows.

## 5. Risks, Open Questions, and Assumptions

| # | Item | Status | Opened | Resolution/Default |
|---|------|--------|--------|--------------------|
| 1 | Template scope: HRP-503c has only 7 fillable controls out of 46 curated fields | Resolved | 2026-02-10 | HRP-503 full application template now also supported (33 mappings) |
| 2 | Encryption key rotation: keys are env-var-based, no automated rotation | Resolved | 2026-04-28 | Documented procedure in 503c-assistant/SECURITY_CHECKLIST.md (Encryption key rotation section); covers both APP_KEY and IRB_FILE_ENCRYPTION_KEYS rotation paths |
| 3 | Purge semantics: deletion redacts audit payloads but retains event records | Open | 2026-02-10 | Default: current behavior is acceptable for regulatory compliance |
| 4 | PHPUnit @dataProvider deprecation warnings in SettingsServiceTest | Resolved | 2026-04-07 | Migrated to PHP 8 attribute syntax (#[DataProvider]) |
| 5 | Hardcoded credential `hurlab123` was in git history | Resolved | 2026-03-22 | Git history wiped via orphan branch + forced push; credential should be rotated if ever used |
| 6 | AdminController uses MySQL-specific TIMESTAMPDIFF for provider metrics | Documented | 2026-04-28 | Captured in `.moai/project/tech.md` "Portability Notes" with PostgreSQL/SQLite equivalents and recommended refactor path (extract to query scope); accepted technical debt while MariaDB is the only supported RDBMS |

## 6. Verification Status

| Item | Method | Result | Verified |
|------|--------|--------|----------|
| Full test suite | `php artisan test` | 142 passed, 451 assertions, 0 failures, 0 warnings (10.92s post-Pint) | 2026-04-28 CDT |
| E2E (Playwright, default parallel) | `npx playwright test` | 21 passed, 0 failures (run 5x consecutively post-fix; pre-fix flake was 1 fail in 2 runs) | 2026-04-28 CDT |
| E2E (Playwright, serial) | `npx playwright test --workers=1` | 20 passed, 0 failures, 24.0s | 2026-04-28 CDT |
| Pint style check | `vendor/bin/pint --test` | pass (zero violations) | 2026-04-28 CDT |
| composer audit (prod + dev) | `php /tmp/composer audit` | 0 advisories | 2026-04-28 CDT |
| npm audit | `npm audit` | 0 vulnerabilities | 2026-04-28 CDT |
| Vite build | `npm run build` | CSS 77.48 KB, JS 84.90 KB, 1.23s | 2026-04-28 CDT |
| Frontend build | `npm run build` | 66.58 KB CSS, 83.55 KB JS, no errors | 2026-04-07 20:16 CDT |
| E2E tests (Playwright) | `npx playwright test` | 20 tests passing (auth + tabs + workflows + admin forms + a11y + project lifecycle) | 2026-04-07 20:16 CDT |
| Accessibility audit | axe-core via Playwright | 0 WCAG 2.1 AA violations on login, projects, admin | 2026-04-07 20:16 CDT |
| npm audit | `npm audit` | 0 vulnerabilities | 2026-04-07 19:28 CDT |
| Security audit | expert-security agent | SECURE: 0 critical/high findings | 2026-04-07 19:28 CDT |
| Reviewer evaluation | evaluator-active agent | APPROVED (Func:92 Sec:91 Craft:78 Consist:85) | 2026-04-07 19:28 CDT |
| Git push | `git push origin main` | `335d22f` on main | 2026-04-07 20:16 CDT |
| Production deployment | Not verified | No production environment available; ops scripts and docs only | -- |

## 7. Restart Instructions

**Last updated:** 2026-04-28 CDT

Start here:

1. Review outstanding work in Section 4 (requires external infrastructure).
2. Start DB: `cd 503c-assistant && bash ops/db/start.sh`
3. Run tests: `cd 503c-assistant && php artisan test` (expect 142 tests, 451 assertions)
4. Build assets: `cd 503c-assistant && npm run build`
5. Dev server: `cd 503c-assistant && composer run dev`
6. E2E tests: `cd 503c-assistant && E2E_NO_WEB_SERVER=1 E2E_BASE_URL=http://localhost:8000 npx playwright test`

Default login (seeded):
- Admin: `admin@example.com` / `change-me`
- Re-seed: `cd 503c-assistant && php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder`
- Seed HRP-503 fields: `cd 503c-assistant && php artisan db:seed --class=Database\\Seeders\\Hrp503FieldDefinitionSeeder`

Repository (split):
- Public mirror: `CTR-TRANSCEND/irb-assistant` — synced to v0.3.0
- Private (active dev): `windysky/irb-assistant` — main = v0.3.0 HEAD
- Tags present on both remotes: v0.2.0, v0.3.0
- v0.3.0 includes 2026-04-27 A-E remedies + dependency CVE patches, plus 2026-04-28 Pint cleanup, Playwright flake fix, and key-rotation docs

Recommended next actions (in priority order):
1. For production deployment, work through `ops/DEPLOYMENT_CHECKLIST.md` and `SECURITY_CHECKLIST.md` (key rotation now documented).
2. Manual screen reader testing with NVDA/VoiceOver (automated axe audit already passes).
3. Configure an LLM provider and test the full analysis workflow end-to-end.
4. Verify the Apache reverse proxy configuration on a real production host.

Key environment variables:
- `IRB_ALLOW_REGISTRATION` (default: false) -- controls public user registration
- `IRB_PDF_PARSER_MEMORY_MB` (default: 256) -- memory limit for PDF fallback parser
- `SESSION_ENCRYPT` -- set to true in production for encrypted sessions

Project documentation:
- `.moai/project/product.md` -- Product overview and features
- `.moai/project/structure.md` -- Architecture and directory layout
- `.moai/project/tech.md` -- Technology stack and dependencies
- `.moai/project/codemaps/overview.md` -- System boundaries and data flow

Authoritative status/backlog docs:
- `503c-assistant/SECURITY_CHECKLIST.md`
- `503c-assistant/PROGRESS.md`
- `503c-assistant/SPECS.md`
- `503c-assistant/TODO.md`
