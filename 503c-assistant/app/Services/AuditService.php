<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Project;
use Illuminate\Http\Request;

class AuditService
{
    public function log(
        Request $request,
        string $eventType,
        ?Project $project = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityUuid = null,
        ?array $payload = null,
    ): void {
        AuditEvent::query()->create([
            'occurred_at' => now(),
            'actor_user_id' => $request->user()?->id,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_uuid' => $entityUuid,
            'project_id' => $project?->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'request_id' => substr((string) $request->header('X-Request-Id'), 0, 64) ?: null,
            'payload' => $payload,
        ]);
    }
}
