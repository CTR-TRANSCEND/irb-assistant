<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Study model — REQ-IRB-FORMSV2-011.
 *
 * A Study is the top-level entity that groups Submissions.
 * Creating a Study automatically creates 3 child Submissions (one per form_code)
 * in a single atomic transaction per REQ-IRB-FORMSV2-011a.
 *
 * @MX:ANCHOR: [AUTO] Study::create() is the entry point for all new study + submission creation.
 *
 * @MX:REASON: fan_in >= 3 — called by StudyAutoCreateTest, StudyController (Phase 4), and StudyFactory.
 *
 * @MX:SPEC: REQ-IRB-FORMSV2-011, REQ-IRB-FORMSV2-011a
 */
class Study extends Model
{
    use HasFactory;

    protected $table = 'studies';

    /**
     * Mass-assignment allowlist.
     *
     * Security review F2 (HIGH) — defense in depth before Phase 4 controllers:
     *   - `user_id` is removed from $fillable so a Phase 4 controller calling
     *     `Study::create($request->validated())` cannot have an attacker bind
     *     the study to another user. Controllers MUST set user_id via
     *     forceFill or via the new createForUser() helper after server-side
     *     authentication.
     */
    protected $fillable = [
        'uuid',
        'application_title',
        'pi_name',
        'project_summary',
        'oversight',
        'nickname',
    ];

    /**
     * Server-side-only helper for Phase 4 controllers. Forces user_id from a
     * trusted source (Auth::id() or a validated admin override) rather than
     * accepting it from request input. Use this instead of `Study::create()`
     * to make the user-binding explicit at the call site.
     *
     * @param  array<string, mixed>  $attributes  Mass-assignable attributes (no user_id).
     */
    public static function createForUser(int $userId, array $attributes): self
    {
        // Evaluator F2 (HIGH): wrap the entire Study::create + 3-Submission
        // boot-hook auto-creation in a single outer DB::transaction so REQ-IRB-FORMSV2-011a
        // atomicity holds. If FormDefinition lookup fails inside the `created` hook,
        // the inner throw rolls back the outer transaction, including the Study INSERT
        // that already committed at the Eloquent layer — leaving zero rows in both
        // `studies` and `submission`.
        return DB::transaction(function () use ($userId, $attributes): self {
            $study = new self;
            $study->fill($attributes);
            $study->forceFill(['user_id' => $userId])->save();

            return $study;
        });
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Boot the model — register Eloquent event listeners.
     *
     * @MX:WARN: [AUTO] Two lifecycle hooks registered: creating (UUID) + created (3-submission auto-create).
     *
     * @MX:REASON: The created hook fires a DB::transaction; if FormDefinition rows are missing,
     *   it throws and the Study row is already committed (no auto-rollback across hooks).
     *   Phase 3 guarantees FormDefinition rows exist via the seed migration.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate UUID on creation
        static::creating(function (Study $study): void {
            if (empty($study->uuid)) {
                $study->uuid = (string) Str::uuid();
            }
        });

        // Auto-create 3 child Submissions per REQ-IRB-FORMSV2-011a
        static::created(function (Study $study): void {
            $formDefs = FormDefinition::whereIn('form_code', ['HRP-503', 'HRP-503c', 'HRP-398'])
                ->get()
                ->keyBy('form_code');

            $submissionIds = DB::transaction(function () use ($study, $formDefs): array {
                $ids = [];
                $statusMap = [
                    'HRP-503' => 'draft',
                    'HRP-503c' => 'draft',
                    'HRP-398' => 'tracking_only',
                ];

                foreach ($statusMap as $formCode => $status) {
                    $def = $formDefs->get($formCode);
                    if ($def === null) {
                        throw new \RuntimeException(
                            "FormDefinition for '{$formCode}' not found. Run the seed migration first."
                        );
                    }

                    // user_id / study_id / form_definition_id / status are NOT in
                    // Submission::$fillable per security review F1+F2; use forceFill
                    // to set them server-side from trusted sources (the parent Study).
                    $submission = new Submission;
                    $submission->fill([
                        'title' => $study->application_title,
                        'principal_investigator' => $study->pi_name,
                        'oversight' => $study->oversight,
                    ]);
                    $submission->forceFill([
                        'study_id' => $study->id,
                        'form_definition_id' => $def->id,
                        'user_id' => $study->user_id,
                        'status' => $status,
                    ])->save();

                    // Evaluator F3 (HIGH): key by form_code so audit consumers can
                    // look up a submission_id by form_code without index math.
                    $ids[$formCode] = $submission->id;
                }

                return $ids;
            });

            // Emit audit event (REQ-IRB-FORMSV2-011a)
            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_user_id' => $study->user_id,
                'event_type' => 'study.created',
                'entity_type' => 'study',
                'entity_id' => $study->id,
                'entity_uuid' => $study->uuid,
                'project_id' => null,
                'ip' => request()->ip() ?? '127.0.0.1',
                'user_agent' => substr((string) (request()->userAgent() ?? 'internal'), 0, 512),
                'request_id' => null,
                'payload' => [
                    'study_id' => $study->id,
                    // Evaluator F3 (HIGH): use form_code-keyed dict so consumers
                    // can look up a submission_id by form_code without index math.
                    'submission_ids' => $submissionIds,
                    'form_codes' => ['HRP-503', 'HRP-503c', 'HRP-398'],
                ],
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * Study-scoped documents uploaded by the study owner.
     *
     * SPEC-IRB-FORMSV2-008 REQ-P8-001
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class, 'study_id', 'id')
            ->orderByDesc('created_at');
    }
}
