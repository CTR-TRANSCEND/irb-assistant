# SPEC: Code Hygiene Fixes

## Problem
Multiple code quality issues: missing strict_types, missing env vars in .env.example, focus ring color inconsistency, explicit CSRF token.

## Solution

### 1. Add `declare(strict_types=1)` to all controllers missing it
Check every PHP file in app/Http/Controllers/ - add strict_types if missing.

### 2. Add missing env vars to .env.example
Add these with appropriate defaults and comments:
- IRB_PDFTOTEXT_TIMEOUT_SECONDS=20
- IRB_PDF_MAX_PAGES=200
- IRB_PDF_MAX_TEXT_BYTES=5242880
- IRB_ANALYSIS_BATCH_SIZE=20
- IRB_MAX_CHUNKS_SENT=40
- IRB_MAX_CHUNK_CHARS_SENT=1200

### 3. Fix focus ring color consistency
In all admin/index.blade.php select/input/textarea elements, change `focus:ring-indigo-500` and `focus:border-indigo-500` to `focus:ring-brand-500` and `focus:border-brand-500` to match the design token system.

### 4. Replace explicit CSRF with @csrf directive
In admin/index.blade.php, replace `<input type="hidden" name="_token" value="{{ csrf_token() }}" />` with `@csrf`.

## Files
- `503c-assistant/app/Http/Controllers/*.php` (strict_types)
- `503c-assistant/.env.example` (env vars)
- `503c-assistant/resources/views/admin/index.blade.php` (focus rings, @csrf)

## Acceptance Criteria
- All controller PHP files have `declare(strict_types=1)`
- All 6 env vars present in .env.example
- All focus rings use brand-500 in admin views
- No explicit CSRF tokens (use @csrf)
- `php artisan test` passes (116 tests)
- `npm run build` succeeds
