# SPEC: Fix Evidence Viewer Double Line Breaks

## Problem
`review-evidence-viewer.blade.php:53` uses both `whitespace-pre-wrap` CSS (which renders newlines as visual line breaks) AND `nl2br()` (which converts newlines to `<br>` tags). This causes every newline to render twice — once from CSS and once from the `<br>` tag.

## Root Cause
The `whitespace-pre-wrap` class on the parent div already handles newline rendering. The `nl2br()` call is redundant and creates doubled line spacing.

## Fix
Remove the `nl2br()` wrapper from line 53. The `{!! !!}` unescaped output is still needed because the ViewModel's `highlightChunkText()` method injects `<mark>` HTML tags after escaping the text via `e()`.

Change: `{!! nl2br($fieldValue['highlighted_chunk']) !!}` → `{!! $fieldValue['highlighted_chunk'] !!}`

## Files
- `resources/views/components/review-evidence-viewer.blade.php`

## Acceptance Criteria
- Newlines in chunk text render as single line breaks (not double)
- `<mark>` highlighting still renders correctly
- Existing test `ReviewTabViewModelTest` still passes
- No XSS: underlying text is already HTML-escaped by ViewModel

## Rollback
Revert the single line change.
