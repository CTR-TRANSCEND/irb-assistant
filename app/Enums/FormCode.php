<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Outstanding #51 — backed enum for the form_code allowlist.
 *
 * Previously the literal `['hrp503', 'hrp503c', 'hrp398']` lived in three
 * places: StoreProjectRequest validation, the projects.form_code ENUM
 * migration, and TemplateService::FORM_TEMPLATES keys. Drift between them
 * could let an attacker store a value the app later rejects (inconsistent
 * state) — not exploitable today, but security-auditor flagged the
 * defense-in-depth gap during the multi-form rollout review.
 *
 * Post-Phase-7 the legacy /projects/* surface is gone; the active callsites
 * are TemplateService + TemplateControlsDumpCommand. The migration ENUM is
 * frozen schema. The (now-dead) StoreProjectRequest is removed in the same
 * PR as this enum.
 */
enum FormCode: string
{
    case Hrp503 = 'hrp503';
    case Hrp503c = 'hrp503c';
    case Hrp398 = 'hrp398';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function templateName(): string
    {
        return match ($this) {
            self::Hrp503 => 'HRP-503',
            self::Hrp503c => 'HRP-503c',
            self::Hrp398 => 'HRP-398',
        };
    }
}
