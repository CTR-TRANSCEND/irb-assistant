<?php

namespace Database\Factories;

use App\Models\DocumentChunk;
use App\Models\ProjectDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentChunkFactory extends Factory
{
    protected $model = DocumentChunk::class;

    public function definition(): array
    {
        return [
            'project_document_id' => ProjectDocument::factory(),
            'chunk_index' => fake()->numberBetween(0, 100),
            'page_number' => fake()->numberBetween(1, 50),
            'source_locator' => 'p'.fake()->numberBetween(1, 50),
            'heading' => fake()->sentence(),
            'text' => fake()->paragraphs(3, true),
            'text_sha256' => fake()->sha256,
            'start_offset' => fake()->numberBetween(0, 1000),
            'end_offset' => fake()->numberBetween(1001, 5000),
        ];
    }
}
