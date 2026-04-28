# SPEC: Repository Hygiene Cleanup

## Problem
1. `tmp/` directory (9 dev debug scripts) not in .gitignore
2. `test.php` stray dev file in project root
3. 150+ stale compiled blade views in `storage/framework/views/`
4. Placeholder `tests/Unit/ExampleTest.php` and `tests/Feature/ExampleTest.php`

## Fix
1. Add `/tmp` and `/test.php` to .gitignore
2. Delete `tmp/` directory and `test.php`
3. Clear stale compiled views via `php artisan view:clear`
4. Delete placeholder ExampleTest files

## Files
- `.gitignore`
- `tmp/` (delete)
- `test.php` (delete)
- `storage/framework/views/` (clear)
- `tests/Unit/ExampleTest.php` (delete)
- `tests/Feature/ExampleTest.php` (delete)

## Acceptance Criteria
- `tmp/` and `test.php` in .gitignore
- Dev files removed from disk
- Compiled views cleared
- Placeholder tests removed
- Test suite still passes (116 - 2 placeholder = 114 tests)

## Rollback
Re-create deleted files from git history if needed.
