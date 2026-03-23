# 503c Assistant

Web app that helps researchers complete an HRP-503c (Human Research / Engagement Determination Application) from an outline + supporting documents.

Core behaviors:
- Upload project documents (DOCX/PDF/TXT).
- Extract and chunk text with metadata.
- Generate a first-pass auto-fill + per-field evidence.
- Guide the user through missing/low-confidence fields via an interactive questionnaire.
- Export a filled HRP-503c DOCX with an evidence trail.

This project is designed to run on a local Linux machine / WSL2 without sudo.

## Quick Start (no sudo)

### 1) Install dependencies

Composer is checked into this repo as `bin/composer`.

```bash
php -v
php bin/composer --version
npm install
```

### 2) Start a local MariaDB (user-space)

This repo includes scripts to run MariaDB inside this project directory (socket-only, no system service).

```bash
./ops/db/start.sh
```

### 3) Configure env

```bash
cp .env.example .env
php artisan key:generate
```

Update DB settings in `.env` if you changed the local socket path.

### 4) Migrate + seed

```bash
php artisan migrate
php artisan db:seed
```

### 5) Build assets + run

```bash
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open: `http://localhost:8000`

### Stop local MariaDB

```bash
./ops/db/stop.sh
```

## Retention cleanup

This app treats uploads/exports as sensitive and supports a retention policy.

Run manually:

```bash
php artisan irb:retention-prune --dry-run
php artisan irb:retention-prune
```

Override retention days:

```bash
php artisan irb:retention-prune --days=7 --dry-run
```

## Apache (reverse proxy)

This app can be deployed behind Apache as a reverse proxy. For local development, `php artisan serve` is enough.

At minimum, ensure Apache forwards `X-Forwarded-Proto` and `X-Forwarded-For`, and Laravel trusts the proxy.

Sample configs + retention scheduling notes:

- `docs/deployment/apache-retention.md`

## Security notes

- Uploaded documents are treated as sensitive and are stored outside the web root.
- Optional application-level encryption-at-rest is available for uploads/exports via `IRB_FILE_ENCRYPTION_KEYS` and `IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID`.
- Analysis run request/response payloads are persisted as redacted JSON plus encrypted full payload columns.
- External LLM providers must be explicitly configured by an admin before use.
- Audit logging is first-class (admin actions + document processing + field edits).

See `SECURITY_CHECKLIST.md`.

## Project status / roadmap

See `PROGRESS.md` for goal/specs, what is implemented, known gaps, and next milestones.

More detail:

- `SPECS.md`
- `TODO.md`
- `docs/API.md`

## Tests

```bash
php artisan test
```

## Playwright E2E (tabs after login)

These browser tests verify that post-login navigation works (Projects + all workspace tabs + Admin tabs).

Prereqs:

```bash
cd 503c-assistant
./ops/db/start.sh

cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed

npm install
```

Run (defaults assume `http://127.0.0.1:8000` and seeded admin `admin@example.com` / `change-me`):

```bash
npm run test:e2e
```

Or use the convenience runner (starts local DB, migrates, seeds admin, installs Chromium, runs tests):

```bash
./ops/e2e/run.sh
```

Optional env overrides:
- `E2E_BASE_URL` (default `http://127.0.0.1:8000`)
- `E2E_EMAIL` / `E2E_PASSWORD`
- `E2E_NO_WEB_SERVER=1` (if you start the app yourself)
- `E2E_WEB_SERVER_COMMAND` (override the default `php artisan serve ...`)
