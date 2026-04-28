# Architecture Overview: IRB-Assistant

## Design Pattern

MVC with Service Layer. Controllers are thin routing handlers; all business logic lives in 11 dedicated service classes.

## System Boundaries

```
┌─────────────────────────────────────────────┐
│              Browser (Blade + Alpine.js)      │
├─────────────────────────────────────────────┤
│     Laravel HTTP Layer (Controllers)          │
│     ├── Auth Controllers (Breeze)             │
│     ├── Project Controllers (CRUD + workflow) │
│     └── Admin Controllers (settings + mgmt)   │
├─────────────────────────────────────────────┤
│     Service Layer (Business Logic)            │
│     ├── Document Pipeline (extract → chunk)   │
│     ├── Analysis Pipeline (LLM → evidence)    │
│     ├── Export Pipeline (template → DOCX)     │
│     ├── Security (encrypt, scan, audit)       │
│     └── Admin (settings, templates, users)    │
├─────────────────────────────────────────────┤
│     Data Layer (Eloquent Models)              │
│     └── 15 models, 21 migrations              │
├─────────────────────────────────────────────┤
│     External Systems                          │
│     ├── MariaDB (user-space)                  │
│     ├── LLM Providers (OpenAI, Ollama, etc.)  │
│     ├── ClamAV (malware scanning)             │
│     └── pdftotext (PDF extraction)            │
└─────────────────────────────────────────────┘
```

## Key Design Decisions

1. **Local-first**: No cloud dependencies; MariaDB runs in user space
2. **Template-driven export**: SDT content controls in DOCX, not string replacement
3. **Evidence traceability**: Every suggestion links to source chunk with byte offsets
4. **Key rotation**: Encryption supports multiple keys via keyring pattern
5. **Graceful degradation**: PDF extraction falls back from pdftotext to PHP parser
6. **Synchronous processing**: Analysis and extraction run inline (no queue)

## Data Flow

1. Document Upload → MalwareScan → Encrypt → Store → Extract → Chunk
2. Analysis Request → Collect Chunks → Build Prompt → LLM Chat → Parse Response → Link Evidence
3. Export Request → Load Template → Map Fields → Fill SDTs → Zip → Encrypt → Store
