<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectDocumentFactory extends Factory
{
    protected $model = ProjectDocument::class;

    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'project_id' => Project::factory(),
            'uploaded_by_user_id' => \App\Models\User::factory(),
            'original_filename' => fake()->sentence(2).'.pdf',
            'storage_disk' => 'local',
            'storage_path' => fake()->filePath(),
            'sha256' => fake()->sha256,
            'mime_type' => 'application/pdf',
            'file_ext' => 'pdf',
            'size_bytes' => fake()->numberBetween(1000, 1000000),
            'kind' => 'protocol',
            'extraction_status' => 'completed',
            'extracted_at' => now(),
        ];
    }
}
