# Draft Migrations

This directory holds DDL migrations that are **NOT yet active**. Laravel's
`php artisan migrate` does NOT scan this directory. To activate a draft, an
operator must `mv` the file into `database/migrations/` before running
`migrate --force`.

## Why drafts?

Some DDL changes (especially DROPs of legacy tables that still hold rows
referenced by `audit_events` or by downstream tooling) carry operational
risk that requires a maintenance window and a fresh DB backup. Committing
the migration here documents the WHEN/HOW for a future operator session
without exposing the irreversible operation to a normal CI deploy.

## Activation

1. Take a fresh DB backup
2. `mv database/migrations-drafts/v1.0.0_drop_legacy_project_schema.php database/migrations/`
3. `php artisan migrate --force`
4. Verify with `SHOW TABLES`

## Rollback

The migration's `down()` is intentionally NOT a `CREATE TABLE …` reverse
(the legacy schema is too coupled to recreate verbatim). Rollback path is:

1. Restore from the pre-DROP backup snapshot
2. `php artisan migrate:rollback --step=1`

---

Created by: SPEC-IRB-FORMSV2-007 LD-P7-2
