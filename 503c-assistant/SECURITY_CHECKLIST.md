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
