# Tech Stack: IRB-Assistant

## Primary Language

PHP 8.3

## Framework

Laravel 12.0 (February 2025)

## Database

MariaDB (local user-space instance via ops/db/start.sh)

## Frontend

- **CSS Framework**: Tailwind CSS 3.1.0 with @tailwindcss/forms
- **JS Framework**: Alpine.js 3.4.2
- **Build Tool**: Vite 7.0.7 with laravel-vite-plugin
- **HTTP Client**: Axios 1.11.0

## Key Dependencies

### Production
| Package | Purpose |
|---------|---------|
| laravel/framework 12.0 | Core framework |
| smalot/pdfparser 2.12 | PHP-based PDF text extraction (fallback) |

### Development
| Package | Purpose |
|---------|---------|
| phpunit/phpunit 11.5 | Unit and feature testing |
| mockery/mockery 1.6 | Test mocking |
| fakerphp/faker 1.23 | Test data generation |
| laravel/breeze 2.3 | Auth scaffolding (Blade stack) |
| laravel/pint 1.24 | PHP code formatting |
| playwright 1.58 | E2E browser testing |

## Security Stack

- **File Encryption**: libsodium XChaCha20-Poly1305 (streaming, AEAD)
- **Malware Scanning**: ClamAV (clamdscan/clamscan)
- **Password Hashing**: Bcrypt (12 rounds)
- **Session**: Database-backed, HTTP-only cookies
- **Rate Limiting**: 5 req/min on auth routes
- **CSRF**: Laravel default middleware

## External Dependencies

- **pdftotext** (poppler-utils): Primary PDF extraction (shell command)
- **unzip/zip**: DOCX template manipulation (shell commands)
- **ClamAV**: Optional malware scanning daemon

## Development Environment

- **DB Management**: Custom ops/db/ scripts (no Docker required)
- **Dev Server**: `composer run dev` (concurrent PHP server + Vite + queue + logs)
- **Tests**: `php artisan test` (PHPUnit 11)
- **E2E**: Playwright with Chromium

## Test Suite

- 142 tests, 451 assertions (PHPUnit, as of v0.3.0 — 2026-04-28)
- Playwright E2E: 21 tests including 2 setup logins and 19 specs
  (auth + tabs + workflows + admin forms + accessibility + project lifecycle)
- Style: Laravel Pint (codebase formatted; `vendor/bin/pint --test` passes)

## Missing Tooling

- No static analysis (PHPStan/Psalm)
- No frontend linting (ESLint/Biome)
- No frontend type checking (TypeScript)
- No code coverage enforcement threshold

## Portability Notes (Database)

The application targets **MariaDB / MySQL** as its single supported RDBMS.
A small number of admin observability queries use vendor-specific SQL
that will not run on PostgreSQL or SQLite without rewrite:

- `App\Http\Controllers\AdminController::index()` uses MySQL-specific
  `TIMESTAMPDIFF(SECOND, started_at, completed_at)` to compute median run
  duration in the analysis runs aggregate. Equivalent on PostgreSQL is
  `EXTRACT(EPOCH FROM (completed_at - started_at))`; on SQLite,
  `(julianday(completed_at) - julianday(started_at)) * 86400`.

Accepted technical debt. The project documents MariaDB as a hard
requirement (see `ops/db/start.sh`, `ops/db/setup-production.sh`), so
porting is only relevant if the supported-database scope changes. If
that happens, prefer extracting the duration arithmetic into a query
scope on the `AnalysisRun` model so the dialect-specific snippet is
isolated.

## Build and Test Commands

| Task | Command |
|------|---------|
| Run PHP test suite | `php artisan test` |
| Run Playwright E2E (parallel) | `npx playwright test` |
| Run Playwright E2E (serial, debug) | `npx playwright test --workers=1` |
| Format with Pint | `vendor/bin/pint` (write) / `vendor/bin/pint --test` (check only) |
| Build assets | `npm run build` |
| Dev server (concurrent) | `composer run dev` |
| Re-seed admin user | `php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder` |
| Re-seed HRP-503 fields | `php artisan db:seed --class=Database\\Seeders\\Hrp503FieldDefinitionSeeder` |
| Composer audit | `composer audit` |
| npm audit | `npm audit` |
