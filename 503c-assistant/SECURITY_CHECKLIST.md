# 503c Assistant - Security Checklist

This app processes sensitive clinical/research documents. Treat this checklist as a minimum bar.

## Auth and session security

- [x] Password hashing uses Laravel's `hashed` cast (bcrypt/argon2 as configured).
- [x] CSRF protection on web forms (Laravel web middleware).
- [x] Session regeneration on login (Breeze).
- [x] Rate limiting on login, registration, and password reset routes (`throttle:5,1`).
- [x] Public registration disabled by default (`IRB_ALLOW_REGISTRATION=false`).
- [x] Role-based admin access enforced via middleware (`admin`).
- [x] Disabled accounts are denied access via middleware (`EnsureUserIsActive`).

## Session security (production)

Set the following in your production `.env`:

```
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
```

- `SESSION_ENCRYPT=true` — encrypts all session data at rest using the application key.
- `SESSION_SECURE_COOKIE=true` — ensures the session cookie is only sent over HTTPS.

## Rate limiting

The following routes are protected by Laravel's built-in throttle middleware:

| Route | Middleware / Limit |
|-------|--------------------|
| `POST /login` | `throttle:5,1` (5 attempts per minute) |
| `POST /register` | `throttle:5,1` |
| `POST /forgot-password` | `throttle:5,1` |
| `POST /reset-password` | `throttle:5,1` |
| `GET|POST /confirm-password` | `throttle:6,1` |
| `PUT /password` (profile password update) | `throttle:6,1` |
| `POST /email/verification-notification` | `throttle:6,1` |
| Export download endpoint | ownership check + standard web throttle |

## Debug mode

`APP_DEBUG` **must be `false`** in production. Leaving it `true` exposes full stack traces, environment variables, and configuration values in error pages.

```
APP_DEBUG=false
```

## Log rotation

Configure Laravel to use the `daily` log channel with 14-day retention to prevent unbounded log growth:

In `.env`:
```
LOG_CHANNEL=daily
```

In `config/logging.php`, confirm the `daily` channel entry includes:
```php
'daily' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/laravel.log'),
    'level'  => env('LOG_LEVEL', 'debug'),
    'days'   => 14,
],
```

## Authorization

- [x] Users can only access their own projects (owner_user_id checks).
- [x] Exports download endpoint validates project ownership.

## Audit logging

- [x] Admin actions logged: provider save/test, settings update, user updates, template upload/activate/mapping.
- [x] Processing actions logged: project created, document uploaded/extracted, analysis run, field updated, export generated/downloaded.
- [x] Sensitive values not logged: provider API keys are hidden and audit payloads redact keys.

## Upload handling

- [x] File upload limits enforced (admin-configurable `max_upload_bytes`).
- [x] File type validation via `mimes:pdf,docx,txt`.
- [x] Stored outside web root (Laravel storage).
- [x] Filenames are not trusted for storage paths (UUID-based stored name).

## LLM privacy controls

- [x] External LLM calls can be disabled globally via admin setting (`allow_external_llm`).
- [x] Analysis refuses to run when policy disallows available providers.

## Retention

- [x] Manual prune command implemented: `php artisan irb:retention-prune` (also supports `--dry-run`).
- [x] Prune command records an audit event.
- [x] Scheduling via Laravel scheduler (`routes/console.php`) - runs daily at 03:00.
- [x] Scheduling also documented via cron example: `ops/cron/503c-assistant.crontab.example`.

## Data at rest

- [x] Application-level encryption-at-rest for uploaded documents and generated exports when `IRB_FILE_ENCRYPTION_KEYS` and `IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID` are configured.
- [x] Analysis run request/response payloads are stored encrypted in parallel text columns; JSON payload columns keep only redacted metadata.
- [ ] Encrypt database volume if possible.

## Encryption key rotation

The application reads three keys via env vars:

- `APP_KEY` — Laravel application key (used for `Crypt::*`, sessions, signed URLs).
- `IRB_FILE_ENCRYPTION_KEYS` — comma-separated `id:base64key` pairs for upload + export encryption (XChaCha20-Poly1305 via `FileEncryptionService`).
- `IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID` — the key id used for new ciphertext writes.

Rotate periodically (e.g., yearly, or after a suspected compromise). Both schemes are designed for in-place rotation without re-encrypting historical data, but the procedures differ.

### Rotating `APP_KEY`

Laravel keeps a list of previous keys in `APP_PREVIOUS_KEYS`. When the current key cannot decrypt a value, framework code falls back through the previous-key list. New writes always use `APP_KEY`.

1. Generate a new key into a scratch variable, e.g.:
   ```bash
   php artisan key:generate --show
   ```
   This prints `base64:...` to stdout without writing anywhere.
2. In production `.env`, move the existing `APP_KEY` value into `APP_PREVIOUS_KEYS` (comma-separated if there are already entries):
   ```
   APP_PREVIOUS_KEYS=base64:OLD_KEY_FROM_STEP_0
   APP_KEY=base64:NEW_KEY_FROM_STEP_1
   ```
3. Reload PHP-FPM / Apache so all worker processes pick up the new env.
4. New sessions, signed URLs, and `Crypt::encrypt` calls now use the new key. Old session cookies and encrypted database columns continue to decrypt via `APP_PREVIOUS_KEYS`.
5. Define a retirement window — for the IRB workload, a sensible default is **one session lifetime + one retention prune cycle** (default `SESSION_LIFETIME=120` minutes + 1 day prune scheduler = ~26 hours). After that window, no live ciphertext should reference the old key.
6. Once the retirement window has elapsed, remove the old key from `APP_PREVIOUS_KEYS` and reload again.

### Rotating `IRB_FILE_ENCRYPTION_KEYS`

`FileEncryptionService` already supports multiple keys via the `id:base64key` format. Old uploads/exports are decrypted by lookup on the embedded key id; new writes use whichever key id is set as `IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID`.

1. Generate a fresh 32-byte key:
   ```bash
   php -r 'echo "base64:".base64_encode(random_bytes(32))."\n";'
   ```
2. Append it to `IRB_FILE_ENCRYPTION_KEYS` with a new id (e.g., `2025a`):
   ```
   IRB_FILE_ENCRYPTION_KEYS="2024a:base64:OLD_KEY,2025a:base64:NEW_KEY"
   ```
3. Switch the active id:
   ```
   IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID=2025a
   ```
4. Reload PHP-FPM / Apache.
5. All new uploads, extractions, and exports now write under the new key id; existing files continue to decrypt under their original key id.
6. Retire the old key id only after every encrypted artifact under that id has been purged or re-encrypted. The simplest path is to wait one full retention window (`IRB_RETENTION_DAYS`, default 14 days, or whatever `settings.retention_days` is set to) so the daily prune job removes everything written under the old id, then drop it from the env list.

### Operational notes

- Treat rotation as a coordinated change: rotate `APP_KEY` and `IRB_FILE_ENCRYPTION_KEYS` separately, with verification between.
- After each rotation, test a known workflow end-to-end (upload → analyze → export → download) to confirm both new writes and old reads still succeed.
- Audit log writes are not encrypted at the application layer — they rely on database-volume encryption for at-rest protection. Plan key rotation alongside DB volume encryption rollout.
- Record each rotation event (date, who performed, key ids retired) in your operations log.

## Malware / unsafe content

- [x] Malware scanning/quarantine step for uploads runs before extraction when `clamdscan` or `clamscan` is available.
- [x] Best-effort behavior: if scanner is unavailable or scan fails, document is marked `unscanned` / `scan_failed` and extraction still proceeds.
- [x] PDF parsing hardening: `pdftotext` timeout, page limits, byte limits, smalot/pdfparser memory limit (`IRB_PDF_PARSER_MEMORY_MB`), fallback logging.

## Hardening / ops

- [x] Sample Apache reverse proxy config + strict headers provided under `ops/apache/`.
- [ ] Production Apache configuration and verification (deployment step).
- [ ] Restrict log verbosity in production and avoid writing document content to logs.
- [ ] Rotate logs and protect backups (set `LOG_CHANNEL=daily`, `days=14` in `config/logging.php`).

## Reverse proxy correctness

- [x] Trusted proxies configured via `TRUSTED_PROXIES` in `bootstrap/app.php`.

## Threat model notes (minimal)

- Data exfiltration: mitigate by external LLM policy + audit logging + retention + least privilege.
- IDOR: mitigate by ownership checks on project and export routes.
- File upload attacks: mitigate by type/size checks + storage isolation + best-effort malware scanning/quarantine.
