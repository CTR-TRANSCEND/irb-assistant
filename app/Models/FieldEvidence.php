<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldEvidence extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_run_id',
        'project_field_value_id',
        'document_chunk_id',
        'excerpt_text',
        'excerpt_sha256',
        'start_offset',
        'end_offset',
    ];

    public function fieldValue(): BelongsTo
    {
        return $this->belongsTo(ProjectFieldValue::class, 'project_field_value_id');
    }

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(DocumentChunk::class, 'document_chunk_id');
    }
}
