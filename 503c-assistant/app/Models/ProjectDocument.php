<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ProjectDocument extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'project_id',
        'uploaded_by_user_id',
        'original_filename',
        'storage_disk',
        'storage_path',
        'is_encrypted',
        'encryption_key_id',
        'sha256',
        'mime_type',
        'file_ext',
        'size_bytes',
        'kind',
        'extraction_status',
        'scan_status',
        'scan_engine',
        'scan_result',
        'scanned_at',
        'scan_error',
        'quarantined_at',
        'quarantine_storage_disk',
        'quarantine_storage_path',
        'extracted_at',
        'extraction_error',
    ];

    protected function casts(): array
    {
        return [
            'extracted_at' => 'datetime',
            'scanned_at' => 'datetime',
            'quarantined_at' => 'datetime',
            'is_encrypted' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }
}
