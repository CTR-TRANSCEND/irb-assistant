# 503c Assistant Specifications

This document defines the intended behavior and current scope of the HRP-503c assistant implemented in `503c-assistant/`.

## Goal

Help users produce a draft HRP-503c (Human Research / Engagement Determination Application) from uploaded project documents by:

- extracting text into traceable chunks,
- generating field suggestions with citations,
- letting users review/edit/confirm answers,
- exporting a filled HRP-503c DOCX from an authoritative template.

## Functional Requirements

### Authentication + authorization

- Users must login before using the app.
- Users may only access their own projects.
- Admin-only area exists for system configuration.
- Admin can disable users; disabled users are denied access.

### Projects

- Users can create projects.
- Project UI provides tabs for Documents, Review, Questions, Export, Activity.

### Document upload and extraction

- Users can upload multiple documents per project.
- Supported upload types: `docx`, `pdf`, `txt`.
- Upload validation must enforce file size limits and type checking.
- Uploaded files must be stored outside the web root.
- Text must be extracted and chunked into `document_chunks` for later evidence linking.

### Templates and mappings

- The system uses an active HRP-503c `.docx` template.
- The template is scanned for Word SDT controls and stored as `template_controls`.
- Admin can map template controls to `field_definitions`.
- Mapping UI must support template parts discovered in the DOCX:
  - `document`, `endnotes`, `footnotes`, and any `headerN`/`footerN` parts.

### LLM providers + policy

- Admin can configure LLM providers.
- Supported provider types in code: `openai`, `openai_compat`, `lmstudio`, `ollama`, `glm47`.
- External providers can be globally disallowed via system setting `allow_external_llm`.
- Provider API keys must not be written to logs or audit events.

### Analysis

- A first-pass analysis can run for a project.
- Analysis must only suggest values for fields that are mapped in the active template.
- Analysis must store an audit event.
- For any non-empty suggested value, analysis must store at least one evidence row linked to a `document_chunk_id`.

### Review + edits

- Review UI must show a field list and detail panel.
- Users can override suggested values and confirm fields.
- Evidence must be visible and deep-linkable, and should show source doc metadata when available.

### Export

- Users can generate and download a DOCX export.
- Export uses the active template and fills mapped SDT controls.
- Export must not blank template content for fields with no value.
- Export should fill controls across supported template parts (not only `word/document.xml`).

### Audit

- Major actions are recorded in an audit log:
  - admin actions: provider save/test, settings, user changes, template upload/activate/mappings
  - project actions: created, document uploaded/extracted, analysis, field updated, export generated/downloaded

### Retention

- The system supports a retention policy for stored uploads and exports.
- A prune command exists and can run in `--dry-run` mode.
- Scheduling is a deployment concern and is documented.

## Non-Functional Requirements

- Must run on local Linux/WSL2.
- Must support a no-sudo mode (user-space MariaDB scripts included).
- Must have an automated test suite that passes locally.

## Acceptance Checks

- `php artisan test` passes.
- Admin can:
  - configure a provider, test it
  - upload a template, map controls, activate it
- User can:
  - create a project
  - upload a TXT document
  - run analysis to get a suggestion + evidence
  - export a filled DOCX

## Implemented (Previously Out of Scope)

- Malware scanning/quarantine for uploads (MalwareScanService, clamdscan/clamscan).
- Encryption-at-rest for stored uploads/derived text (FileEncryptionService, XChaCha20-Poly1305).
- A bundled, authoritative mapping pack from HRP-503c template to curated `hrp503c.*` fields (7 field mappings).

## Out of Scope (Not Implemented Yet)

- Rich DOCX control behaviors (checkbox/date/richtext semantics beyond inserting text).
