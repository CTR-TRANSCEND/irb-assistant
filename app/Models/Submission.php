<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidSubmissionStateTransition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Submission model — maps to `submission` table (singular per CANONICAL_SCHEMA.sql).
 *
 * One Submission per (study_id, form_definition_id) pair (UNIQUE constraint per REQ-IRB-FORMSV2-013).
 * Denormalized snapshot of Study title/pi/oversight per REQ-IRB-FORMSV2-006.
 *
 * Status transitions enforced via markStatus() per REQ-IRB-FORMSV2-014a:
 *   - tracking_only is a terminal state; no transition is allowed out of it.
 *
 * @MX:ANCHOR: [AUTO] markStatus() is the single status transition enforcer for all Submission rows.
 *
 * @MX:REASON: fan_in >= 3 — called by Hrp398TrackingOnlyTest, SubmissionController (Phase 4), and future workflow services.
 *
 * @MX:SPEC: REQ-IRB-FORMSV2-013, REQ-IRB-FORMSV2-014a, REQ-IRB-FORMSV2-006
 */
class Submission extends Model
{
    use HasFactory;

    protected $table = 'submission';

    /**
     * Mass-assignment allowlist.
     *
     * Security review F2 (HIGH) — defense in depth before Phase 4 controllers:
     *   - `user_id`, `study_id`, `form_definition_id` are removed from $fillable so
     *     Phase 4 controllers calling `Submission::create($request->validated())` or
     *     `$submission->update($request->all())` cannot have an attacker rebind
     *     ownership, parent study, or form template. These are set server-side via
     *     forceFill() in the Study auto-create hook.
     *   - `status` is removed because tracking_only is a terminal state enforced
     *     server-side via the saving-event listener (security review F1). Phase 4
     *     controllers MUST use markStatus() to mutate status.
     */
    protected $fillable = [
        'assistance_mode',
        'title',
        'principal_investigator',
        'oversight',
        'routing_outcome',
        'routing_outcome_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Valid status values per CANONICAL_SCHEMA.sql + REQ-IRB-FORMSV2-014a.
     */
    public const VALID_STATUSES = [
        'draft',
        'submitted',
        'under_review',
        'approved',
        'rejected',
        'withdrawn',
        'tracking_only',
    ];

    /**
     * Saving event listener — enforces the tracking_only terminal-state invariant
     * for ALL persistence paths (mass assignment, direct attribute assignment,
     * factories), not just markStatus(). Security review F1 (HIGH).
     *
     * REQ-IRB-FORMSV2-014a: once a Submission's status is `tracking_only`,
     * SHALL NOT transition to any other value.
     *
     * The original value is the row as currently persisted (or null for a new
     * row). For new rows, this listener is a no-op. For existing rows where
     * the original status is `tracking_only`, this listener rejects any attempt
     * to change `status` to a different value — regardless of which Eloquent
     * method (update, save, fill, attribute assignment) initiated the write.
     *
     * @MX:ANCHOR: Submission saving listener is the single chokepoint for the
     *             tracking_only invariant. DB-level CHECK constraint is a
     *             follow-up (security review F1 PR-2 recommendation).
     */
    protected static function booted(): void
    {
        static::saving(function (self $submission): void {
            $originalStatus = $submission->getOriginal('status');
            $newStatus = $submission->status;

            if ($originalStatus === 'tracking_only' && $newStatus !== 'tracking_only') {
                throw new InvalidSubmissionStateTransition(
                    "Submission #{$submission->id} has original status 'tracking_only' which is a terminal state. "
                    ."Refusing to change status to '{$newStatus}' per REQ-IRB-FORMSV2-014a."
                );
            }

            if ($newStatus !== null && ! in_array($newStatus, self::VALID_STATUSES, true)) {
                throw new InvalidSubmissionStateTransition(
                    "'{$newStatus}' is not a valid submission status."
                );
            }
        });
    }

    /**
     * Attempt a status transition on this Submission.
     *
     * Business rule (REQ-IRB-FORMSV2-014a):
     *   - If current status is 'tracking_only', NO transition is allowed.
     *
     * @throws InvalidSubmissionStateTransition when transition is rejected.
     */
    public function markStatus(string $newStatus): void
    {
        if ($this->status === 'tracking_only') {
            throw new InvalidSubmissionStateTransition(
                "Submission #{$this->id} has status 'tracking_only' which is a terminal state. "
                .'No status transition is permitted per REQ-IRB-FORMSV2-014a.'
            );
        }

        if (! in_array($newStatus, self::VALID_STATUSES, true)) {
            throw new InvalidSubmissionStateTransition(
                "'{$newStatus}' is not a valid submission status."
            );
        }

        // status is NOT in $fillable per security review F1; use forceFill.
        $this->forceFill(['status' => $newStatus])->save();
    }

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    public function formDefinition(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SubmissionAnswer::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(SubmissionUpload::class);
    }

    public function worksheetAssistStates(): HasMany
    {
        return $this->hasMany(WorksheetAssistState::class);
    }
}
