# SPEC: Fix NPM Dependency Vulnerabilities

## Problem
`npm audit` reports 4 HIGH severity Vite vulnerabilities (path traversal, fs.deny bypass, WebSocket file read).

## Solution
Run `npm audit fix` to update Vite to a patched version. If `npm audit fix` doesn't resolve all issues, manually update the vite package version.

## Files
- `503c-assistant/package.json`
- `503c-assistant/package-lock.json`

## Acceptance Criteria
- `npm audit` reports 0 high/critical vulnerabilities
- `npm run build` succeeds
- No changes to application behavior

## Rollback
Revert package.json and package-lock.json changes.
