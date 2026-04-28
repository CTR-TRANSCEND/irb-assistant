# SPEC: Admin Form UX Improvements

## Problem
Admin forms have UX gaps: missing old() preservation on textarea, missing validation error display on 5 fields, missing loading states on 6 forms.

## Solution

### 1. Add old() value preservation
In admin/index.blade.php, the request_params_json textarea should preserve user input on validation failure:
`{{ old('request_params_json') }}`

### 2. Add validation error display
Add `<x-input-error :messages="$errors->get('field_name')" class="mt-2" />` after these fields in the provider form:
- provider_type select
- base_url input
- model input
- api_key input
- provider name input

### 3. Add loading states to remaining forms
Add Alpine.js x-data loading state (like the pattern in projects/show.blade.php) to:
- Provider update form (projects/show.blade.php)
- Field value update form (projects/show.blade.php)
- Provider add form (admin/index.blade.php)
- Template upload form (admin/index.blade.php)
- Settings save form (admin/index.blade.php)

Pattern: `x-data="{ loading: false }" @submit="loading = true"` with button showing spinner when loading.

## Files
- `503c-assistant/resources/views/admin/index.blade.php`
- `503c-assistant/resources/views/projects/show.blade.php`

## Acceptance Criteria
- old() value appears in textarea after validation failure
- All 5 provider form fields show validation errors
- All 6 forms show loading state during submission
- `npm run build` succeeds
- `php artisan test` passes
