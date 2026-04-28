# SPEC: Add Max Length Validation to Field Value Input

## Problem
`ProjectFieldController::update()` validates `final_value` as `['nullable', 'string']` with no length limit. While the DB column is `longText`, unbounded input is a defense-in-depth gap.

## Fix
Add `'max:65535'` to the `final_value` validation rules. This is generous enough for any realistic IRB form field while preventing multi-MB abuse.

## Files
- `app/Http/Controllers/ProjectFieldController.php`

## Acceptance Criteria
- `final_value` validation includes max:65535
- Existing tests pass
- Fields up to 65535 chars accepted; longer rejected with 422

## Rollback
Remove the max rule.
