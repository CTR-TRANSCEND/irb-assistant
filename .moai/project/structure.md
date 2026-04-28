# Structure: IRB-Assistant

## Architecture Pattern

MVC (Model-View-Controller) with Service Layer. Laravel 12 conventions with dedicated service classes for business logic separation.

## Directory Layout

```
503c-assistant/
├── app/
│   ├── Http/
│   │   ├── Controllers/        # 13 controllers (5 project, 5 admin, auth)
│   │   │   └── Auth/           # 8 Breeze-generated auth controllers
│   │   ├── Middleware/          # 2 custom: EnsureUserIsActive, EnsureUserIsAdmin
│   │   └── Requests/           # 2 form requests: LoginRequest, ProfileUpdateRequest
│   ├── Models/                 # 15 Eloquent models
│   ├── Services/               # 11 service classes (~3000 LOC)
│   ├── ViewModels/             # 1 view model: ReviewTabViewModel
│   └── Providers/              # AppServiceProvider
├── config/                     # Laravel config files (standard)
├── database/
│   ├── migrations/             # 21 migrations
│   ├── factories/              # Model factories
│   └── seeders/                # AdminUserSeeder, FieldDefinitionSeeder
├── resources/
│   ├── views/
│   │   ├── layouts/            # app, guest, navigation
│   │   ├── components/         # 15+ Blade components
│   │   ├── projects/           # index, show (5-tab workspace)
│   │   ├── admin/              # index (6-tab admin panel)
│   │   ├── auth/               # Login, register, password reset
│   │   └── profile/            # User profile management
│   ├── css/app.css             # Custom design system (badges, cards, tabs, alerts)
│   └── js/                     # Alpine.js + Axios setup
├── routes/
│   ├── web.php                 # 25+ application routes
│   ├── auth.php                # 15 auth routes with rate limiting
│   └── console.php             # Scheduled retention command
├── tests/
│   ├── Unit/                   # 10 service test files
│   ├── Feature/                # 8 feature test files
│   └── e2e/                    # 2 Playwright test files
├── ops/
│   ├── db/                     # MariaDB management scripts
│   ├── e2e/                    # E2E test runner
│   ├── apache/                 # Production Apache config
│   └── cron/                   # Scheduler crontab
└── docs/                       # Research documents (gitignored)
```

## Key Service Responsibilities

| Service | Responsibility |
|---------|---------------|
| AuditService | Event logging with request context |
| SettingsService | System settings CRUD with caching |
| LlmChatService | Multi-provider LLM chat completions |
| ProjectInitializationService | Field value scaffolding for new projects |
| TemplateService | DOCX template parsing, control extraction, mapping |
| MalwareScanService | ClamAV file scanning |
| FileEncryptionService | XChaCha20-Poly1305 file encryption/decryption |
| DocumentExtractionService | PDF/DOCX/TXT text extraction with chunking |
| ProjectPurgeService | Cascading project deletion with audit redaction |
| DocxExportService | DOCX generation from template + field values |
| ProjectAnalysisService | LLM-driven field suggestion with evidence linking |

## Data Model Summary

- **User** -> owns Projects
- **Project** -> has Documents, FieldValues, AnalysisRuns, Exports, AuditEvents
- **ProjectDocument** -> has DocumentChunks (extracted text segments)
- **FieldDefinition** -> defines form fields (template-agnostic)
- **ProjectFieldValue** -> per-project field answers with status tracking
- **FieldEvidence** -> links field values to source document chunks
- **TemplateVersion** -> has TemplateControls and TemplateControlMappings
- **LlmProvider** -> configurable AI providers
- **AuditEvent** -> immutable action log
