# IRB Assistant

> [!CAUTION]
> **Under active development.** This project is being piloted at one institution and is **not yet ready for general production use** at other sites. APIs, schemas, and UI may change without notice.

An AI-assisted web application that helps researchers draft three IRB protocol forms from uploaded study documents:

- **HRP-503** &mdash; full Human Research Protocol application
- **HRP-503c** &mdash; Human Research Engagement Determination
- **HRP-398** &mdash; AI Considerations Worksheet (guidance only, not submitted to the IRB)

A single **Study** automatically creates three submissions &mdash; one per form. Documents you upload to the Study are shared across all submissions and drive the AI analysis on each form.

**Live deployment:** [https://ignet.org/irb-assistant/](https://ignet.org/irb-assistant/) &mdash; pilot tenancy at the University of North Dakota with Sanford Health, funded by NIH/NIGMS through the [TRANSCEND RDCDC](https://transcendrdcdc.org/) (P20GM155890).

---

## How it works

```
                  +-------------------------------------------+
                  |  Study (one umbrella per research project)|
                  +----------+--------+---------------+-------+
                             |        |               |
                             v        v               v
                       HRP-503    HRP-503c        HRP-398
                       Submission Submission     Submission
                       (full app) (engagement)   (AI worksheet)
                             ^        ^               ^
                             |        |               |
                             +--------+---------------+
                                      |
                                      |  Shared documents (PDF / DOCX / TXT)
                                      |  Encrypted at rest, malware-scanned
                                      v
                       +----------------------------------+
                       |  1. Upload study documents       |
                       |  2. Run AI Analyze on a form     |
                       |     - Evidence extraction        |
                       |     - Optional Assistant drafts  |
                       |  3. Review + edit suggestions    |
                       |  4. Export filled DOCX           |
                       +----------------------------------+
```

The AI analysis runs in two modes per submission:

- **Strict mode (default for audit-grade workflows).** The LLM only proposes answers it can ground in a verbatim quote from your uploaded documents. Every suggestion includes a chunk-level evidence pointer. Fields with no supporting evidence stay blank for you to fill manually.
- **Assistant mode.** On top of evidence-grounded answers, the LLM generates plain-language draft answers for fields with insufficient evidence, with explicit `[SPECIFY: ...]` placeholders for anything it would otherwise have to invent. Drafts are clearly distinguished from evidence-grounded answers in the UI (amber border, explicit "Accept draft" button).

The Analyze button kicks off a queued background job. A real-time progress modal opens automatically and polls the job state every 2 seconds &mdash; pressable **Esc** dismisses the modal but keeps polling, **Cancel** stops the job at the next checkpoint, and partial results that already saved are kept.

---

## Key features

- **Three-form multi-submission model** &mdash; HRP-503, HRP-503c, HRP-398 share Study-level documents and run independently.
- **Evidence-backed suggestions** &mdash; every LLM proposal links to a verbatim chunk from your source PDFs; quote-in-chunk match is enforced server-side.
- **Real-time analysis modal** with Esc-dismiss, cancel, and step-by-step progress (Prepare &rarr; Extract evidence &rarr; AI drafts &rarr; Save).
- **Section-level navigation** for HRP-503's 248 questions across 43 sections, with cross-section trigger gating (sections lock or unlock based on earlier answers).
- **Encryption at rest** &mdash; uploaded documents and LLM payloads are encrypted with XChaCha20-Poly1305; keyring supports rotation.
- **Malware scanning** via ClamAV with graceful fallback for hosts without ClamAV installed.
- **Self-registration &rarr; admin approval workflow** &mdash; new users are blocked from login until an admin reviews and approves their account.
- **Multi-provider LLM** &mdash; OpenAI, OpenAI-compatible (e.g. LM Studio, Ollama, GLM 4.7), with per-tenant SSRF allow-list and audit-redacted base URLs.
- **Audit log** &mdash; every significant action (auth, upload, analysis, export, admin) is recorded with request context.
- **Template-driven DOCX export** that fills Word content controls (SDTs) in the official HRP-503 / HRP-503c templates.
- **WCAG 2.1 AA-aware UI** &mdash; skip-to-content links, ARIA semantics, dark-mode parity, keyboard-accessible modals, per-page titles.
- **Retention management** &mdash; automated daily cleanup of expired uploads and exports.

---

## Tech stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 &middot; PHP 8.3 (dev) / PHP 8.2 (prod via Remi) |
| Database | MariaDB 10.x &middot; MySQL 8 |
| Queue | Laravel Queue (Redis driver) with systemd worker |
| Frontend | Blade &middot; Tailwind CSS &middot; Alpine.js |
| Build | Vite (self-hosted Inter via `@fontsource/inter`) |
| Tests | PHPUnit (456 tests / 1,405 assertions) &middot; Playwright E2E (21 specs) |
| LLM (pilot) | LM Studio on DGX Spark via Tailscale &middot; gemma-4-e4b at 16K context |

---

## Quick start (local development)

### Prerequisites

- PHP 8.3+
- Node.js 18+
- MariaDB 10.x or MySQL 8
- Redis (for the queue)
- An LLM endpoint &mdash; OpenAI key, or any OpenAI-compatible local server (LM Studio, Ollama)

### Setup

```bash
git clone https://github.com/windysky/irb-assistant.git
cd irb-assistant

# Start a user-space MariaDB instance (no sudo required)
./ops/db/start.sh

# Configure environment
cp .env.example .env
php artisan key:generate

# Install dependencies
composer install
npm ci

# Initialize the database (migrations + seed admin + bundled HRP templates)
php artisan migrate --seed

# Build frontend assets
npm run build

# Start the dev server
php artisan serve --host=127.0.0.1 --port=8000

# In a second terminal, start the queue worker
php artisan queue:work --tries=1 --timeout=1800
```

Open [http://localhost:8000](http://localhost:8000).

### Default seeded admin

| Field | Value |
|---|---|
| Email | `admin@local` |
| Password | `change-me` |

Change this immediately after first login. To re-seed:

```bash
php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder
```

### Configure an LLM provider

Sign in as admin, go to **Admin &rarr; LLM Providers**, click **Add provider**, and enter:

- **Provider type** &mdash; OpenAI, OpenAI-compatible, LM Studio, Ollama, or GLM 4.7
- **Base URL** &mdash; e.g. `http://127.0.0.1:1234/v1` for a local LM Studio
- **Model** &mdash; e.g. `google/gemma-4-e4b`
- **API key** &mdash; if required

For **LM Studio** specifically, load the model with a 16K+ context window:

```bash
lms unload google/gemma-4-e4b
lms load   google/gemma-4-e4b --context-length 16384 --gpu max -y
```

The default prompt config sends up to 40 evidence chunks with 1,200 chars each, plus 20 questions per batch. A 4K context is too small for non-trivial HRP forms.

---

## Configuration

Environment variables (see `.env.example` for the full list):

| Variable | Default | Description |
|---|---|---|
| `IRB_PDF_PARSER_MEMORY_MB` | `256` | Memory limit for the PDF fallback parser |
| `IRB_FILE_ENCRYPTION_KEYS` | &mdash; | Pipe-separated keyring for file at-rest encryption |
| `IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID` | &mdash; | Active key ID for new encryptions |
| `IRB_DRAFTING_MAX_PER_RUN` | `20` | Max AI-drafted answers per Assistant-mode run |
| `IRB_ALLOW_LLM_LOOPBACK` | `false` | Local-dev only &mdash; permits 127.0.0.1 base URLs for LLM providers |
| `IRB_LLM_HTTP_TIMEOUT` | `600` | LLM HTTP request timeout (seconds) |
| `SESSION_LIFETIME` | `60` | Idle session timeout (minutes) |
| `SESSION_EXPIRE_ON_CLOSE` | `true` | Sessions invalidate when browser closes |

### Sub-path deployment

The app supports deployment under a URL prefix (e.g. `/irb-assistant` on a multi-tenant host). Set:

```bash
APP_URL=https://example.org/irb-assistant
```

…and build assets with the matching prefix so the CSS `url()` font references resolve correctly:

```bash
VITE_APP_BASE=/irb-assistant/build/ npm run build
```

---

## Authentication and authorisation

- **Registration is public by default**, but new accounts are created with `is_approved=false` and cannot log in until an admin clicks **Approve** on the **Admin &rarr; Users** tab.
- Sessions idle-expire after 60 minutes and invalidate when the browser closes &mdash; no "Remember me" option is offered. This is intentional for handling study PII.
- Password reset is rate-limited (5 attempts per minute), as are login, registration, document upload, and analysis dispatch.
- Admins have a non-deletable "Demote" action that prevents accidental lockout.

---

## Testing

```bash
# Full PHPUnit suite (456 tests, 1,405 assertions)
php artisan test

# E2E (Playwright)
npx playwright test

# Smoke test against a live LM Studio instance over Tailscale (opt-in)
IRB_RUN_LIVE_LLM=1 npx playwright test lm-studio-smoke.spec.ts --workers=1
```

CI runs the full suite + `vendor/bin/pint --test` + `npm run build` on every push.

---

## Security

- Documents stored outside the web root with optional XChaCha20-Poly1305 encryption.
- Malware scanning via ClamAV; gracefully falls back to a quarantine-only mode when ClamAV is not installed.
- LLM provider **base URL** is DNS-resolved server-side and rejected if it points at private IP space, IPv6 loopback / link-local / ULA, 6to4 / Teredo translation prefixes, non-decimal IPv4 literals, or IPv4-mapped IPv6. Tailscale's 100.64.0.0/10 range is allowed by design. See [`SECURITY_CHECKLIST.md`](SECURITY_CHECKLIST.md) for the full SSRF posture.
- Rate limiting on all auth + sensitive routes (5 requests per minute).
- LLM request and response payloads are stored with sensitive parts redacted in the JSON column; the full payload is kept encrypted for audit.
- Audit log covers auth events, document uploads, analysis runs, exports, and all admin actions.
- Project deletion redacts audit payloads while preserving event records (regulatory-friendly).
- @MX code-level annotations document invariants, danger zones, and incomplete work for downstream AI agents.

For the full security posture see [`SECURITY_CHECKLIST.md`](SECURITY_CHECKLIST.md) and the deploy playbook at [`ops/DEPLOYMENT_CHECKLIST.md`](ops/DEPLOYMENT_CHECKLIST.md).

---

## Production deployment

The reference production deployment is on RHEL 9 + Apache 2.4 + PHP 8.2 (Remi side-by-side) + MariaDB. The public templates in [`ops/apache/`](ops/apache/) and [`ops/db/`](ops/db/) cover the vhost, FPM pool, and database setup. Site-specific deploy automation (rsync wrapper, vhost installer, secrets handling) is maintained in a separate internal repository.

The queue worker is supervised by systemd:

```ini
# /etc/systemd/system/irb-queue.service
[Service]
ExecStart=/opt/remi/php82/root/usr/bin/php artisan queue:work --tries=1 --timeout=1800 --sleep=3 --max-time=3600
WorkingDirectory=/data/var/www/html/irb-assistant
```

---

## Project structure

```
.
+-- app/
|   +-- Console/Commands/        # Retention prune, template control dump
|   +-- Enums/                   # FormCode backed enum (hrp503 | hrp503c | hrp398)
|   +-- Http/Controllers/        # Auth, Study, Submission, Admin, Export
|   +-- Http/Middleware/         # EnsureUserIsAdmin, EnsureUserIsActive
|   +-- Jobs/                    # AnalyzeSubmissionJob (queued LLM pipeline)
|   +-- Models/                  # Study, Submission, SubmissionAnswer, FormDefinition, ...
|   +-- Services/                # SubmissionAnalysisService, LlmChatService,
|                                #   AnswerValidator, ConditionalVisibilityEvaluator,
|                                #   SubmissionDraftingService, SubmissionDocxExportService,
|                                #   FileEncryptionService, MalwareScanService, AuditService
+-- database/
|   +-- migrations/              # Phase 3 canonical schema (studies + submissions)
|   +-- seeders/                 # Admin, FormDefinition, Hrp398FieldDefinitions, Templates
+-- resources/
|   +-- templates/               # HRP-503.docx + HRP-503c.docx + HRP-398.docx
|   +-- views/                   # Blade (studies, submissions, admin, auth, layouts)
|   |   +-- submissions/types/   # 24 question-type partials (radio, checkbox, textarea, ...)
+-- routes/                      # web.php (thin) + auth.php
+-- tests/
|   +-- Feature/FormsV2/         # Phase 3/4/5/6/8 integration tests
|   +-- Unit/                    # Service + helper tests
+-- ops/
|   +-- db/                      # User-space MariaDB start/stop scripts
|   +-- apache/                  # Apache vhost samples
+-- ops/                         # Public deploy templates (Apache vhost, FPM pool, MariaDB user-space scripts)
```

---

## Roadmap

Pilot phase is closed-loop with the UND clinical research group + Sanford collaborators. After the pilot we plan:

- Per-field version history (every edit preserved, not just current value).
- Staging environment alongside production.
- Sentry error tracking + UptimeRobot HTTP monitoring.
- Curated `field_guidance` table &mdash; plain-English explainer + redacted example + common pitfalls per HRP field.
- Optional SSO via institutional Shibboleth/SAML.

Open work is tracked internally.

---

## Credits

Developed by **Dr. Junguk Hur** ([@hurlab](https://www.hurlab.com/)) at the [University of North Dakota School of Medicine and Health Sciences](https://med.und.edu/) in collaboration with **Sanford Health**.

This work is supported by **NIH/NIGMS** through the [TRANSCEND RDCDC](https://transcendrdcdc.org/) (P20GM155890).

---

## License

This project is currently distributed under a research-pilot licence. Contact the maintainer for evaluation use. See [`LICENSE`](LICENSE) once added.
