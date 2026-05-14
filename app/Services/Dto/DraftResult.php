<?php

declare(strict_types=1);

namespace App\Services\Dto;

/**
 * SPEC-IRB-GUIDE-001 M1 — Immutable result of a single LlmDraftingService::draftMissingField() call.
 *
 * REQ-IRB-GUIDE-006: returned by draftMissingField(); isEmpty=true signals failure or empty response.
 * REQ-IRB-GUIDE-013: warnings contains unverified-specific flags from the anti-fabrication post-scan.
 * REQ-IRB-GUIDE-024: promptForAudit is stored verbatim in the audit row's payload.
 */
readonly class DraftResult
{
    /**
     * @param  list<array{type: string, token: string}>  $warnings
     */
    public function __construct(
        /** The drafted text, trimmed. Empty string when isEmpty=true. */
        public string $value,
        /** True when the model returned an empty/whitespace response OR the chat call threw. */
        public bool $isEmpty,
        /** Anti-fabrication post-scan flags (unverified numerical/date tokens). */
        public array $warnings,
        /** Verbatim system+user prompt string stored in the audit row. REQ-IRB-GUIDE-026. */
        public string $promptForAudit,
        /** The provider's model identifier, stored in the audit row. */
        public string $modelUsed,
    ) {}
}
