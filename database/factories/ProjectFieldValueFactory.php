<?php

namespace Database\Factories;

use App\Models\FieldDefinition;
use App\Models\Project;
use App\Models\ProjectFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFieldValueFactory extends Factory
{
    protected $model = ProjectFieldValue::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'field_definition_id' => FieldDefinition::factory(),
            'suggested_value' => fake()->sentence(),
            'final_value' => null,
            'status' => 'suggested',
            'confidence' => fake()->randomFloat(2, 0.5, 1.0),
            'suggested_at' => now(),
            'confirmed_at' => null,
            'updated_by_user_id' => null,
        ];
    }
}
