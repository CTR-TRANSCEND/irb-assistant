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

- 114 tests, 363 assertions
- Unit tests: 10 service files (50 methods)
- Feature tests: 8 files (17 methods)
- Auth tests: 18 methods (Breeze-generated)
- E2E: 2 Playwright files (basic tab navigation)

## Missing Tooling

- No static analysis (PHPStan/Psalm)
- No frontend linting (ESLint/Biome)
- No frontend type checking (TypeScript)
- No code coverage enforcement threshold
