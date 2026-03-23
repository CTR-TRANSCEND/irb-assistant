# Todo (Backlog)

This is the remaining work after the current implementation.

## Completed (moved from backlog)

- [x] Mapping pack for HRP-503c.docx (7 curated `hrp503c.*` key mappings bundled)
- [x] Evidence fidelity: quote-in-chunk enforcement with offsets
- [x] Security: malware scanning/quarantine (MalwareScanService)
- [x] Security: encryption-at-rest (FileEncryptionService, XChaCha20-Poly1305)
- [x] Per-project provider selection
- [x] Project deletion/purge UI with audit redaction
- [x] PDF parsing hardening with configurable memory limits and pdftotext fallback

## Medium priority

- Export enhancements:
  - better handling for multi-paragraph text and long insertions
  - broader template part support verification on real HRP-503c variants
  - checkbox/date/richtext SDT control types
- Mapping assistance improvements:
  - label similarity suggestions for unmapped controls
  - per-control drift report (matched/missed) on template upload

## Low priority

- Add more admin observability pages (analysis run viewer, provider usage stats).
- Broader template support (HRP-503 full application form).
- Deeper accessibility (tab roles, form aria-describedby, remaining decorative SVGs).
