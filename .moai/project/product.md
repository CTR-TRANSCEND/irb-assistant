# Product: IRB-Assistant

## Overview

IRB-Assistant is a local-first web application that helps researchers draft HRP-503c (Human Research Engagement Determination) IRB forms from uploaded study documents. It uses LLM-powered analysis to suggest field values backed by traceable evidence from source documents.

## Target Audience

- University researchers preparing IRB submissions
- Research compliance officers reviewing HRP-503c forms
- Institutional administrators managing LLM providers and templates

## Core Workflow

Upload Documents -> Extract & Chunk -> LLM Analysis -> Review & Edit -> Export .docx

1. Upload study documents (DOCX, PDF, TXT)
2. Automatic text extraction with chunking and metadata
3. LLM analysis generates field suggestions with evidence quotes
4. Review each suggestion with side-by-side evidence browsing
5. Export a completed HRP-503c DOCX with approved answers

## Core Features

- **Evidence-backed suggestions**: Every LLM suggestion includes traceable quotes with chunk-level provenance
- **Encryption at rest**: Uploaded files encrypted using XChaCha20-Poly1305 (libsodium)
- **Malware scanning**: ClamAV integration for upload scanning
- **Audit logging**: All actions recorded with request context, IP, user agent
- **Multi-provider LLM**: OpenAI, OpenAI-compatible, LM Studio, Ollama, GLM 4.7
- **Template-driven export**: Fills Word content controls (SDTs) in official HRP-503c template
- **Retention management**: Automated daily cleanup of expired documents and exports
- **Role-based access**: Admin and user roles with per-project ownership

## User Roles

- **Admin**: Manages LLM providers, templates, users, system settings, views audit logs
- **User**: Creates projects, uploads documents, runs analysis, reviews fields, exports DOCX

## Project Status

Feature-complete for HRP-503c workflows. Tagged v0.1.0. Under active development for broader template support and UI improvements.
