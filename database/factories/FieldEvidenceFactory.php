<?php

namespace Database\Factories;

use App\Models\DocumentChunk;
use App\Models\FieldEvidence;
use App\Models\ProjectFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

class FieldEvidenceFactory extends Factory
{
    protected $model = FieldEvidence::class;

    public function definition(): array
    {
        $chunkText = fake()->paragraph();

        return [
            'project_field_value_id' => ProjectFieldValue::factory(),
            'document_chunk_id' => DocumentChunk::factory(),
            'excerpt_text' => fake()->sentence(6),
            'excerpt_sha256' => hash('sha256', fake()->sentence()),
            'start_offset' => fake()->numberBetween(0, 100),
            'end_offset' => fake()->numberBetween(101, 500),
        ];
    }
}
