<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a worksheet assist-state item's status transitions to a new value.
 *
 * Consumers (future audit listeners) can react to status changes without
 * coupling the controller to audit logic.
 *
 * SPEC-IRB-FORMSV2-006 §C.1
 */
class WorksheetItemStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $submissionId,
        public readonly string $itemId,
        public readonly ?string $oldStatus,
        public readonly string $newStatus,
    ) {}
}
