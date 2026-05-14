<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SubmissionUpload — file attachment for a Submission.
 *
 * Per REQ-IRB-FORMSV2-008: a Study's uploaded documents are visible
 * to all child Submissions. Phase 3 only persists the relationship;
 * Phase 4 wires the shared-visibility UI.
 */
class SubmissionUpload extends Model
{
    use HasFactory;

    protected $table = 'submission_upload';

    /**
     * Mass-assignment allowlist.
     *
     * Security review F6 (MEDIUM) — defense in depth before Phase 4 upload controllers:
     *   - `submission_id` removed so a Phase 4 controller using
     *     `SubmissionUpload::create($request->validated())` cannot bind the
     *     upload to another user's submission (IDOR).
     *   - `storage_path` removed so an attacker cannot supply a path-traversal
     *     value (`../../etc/passwd`); Phase 4 controllers MUST set this via
     *     `forceFill` after `Storage::putFileAs()` returns the canonical path.
     *   - `original_filename` MUST be sanitized via basename() by the caller
     *     before storage (Phase 4 concern; documented here for the reviewer).
     */
    protected $fillable = [
        'question_key',
        'original_filename',
        'mime_type',
        'file_size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
