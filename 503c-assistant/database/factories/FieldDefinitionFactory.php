<?php

namespace Database\Factories;

use App\Models\FieldDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class FieldDefinitionFactory extends Factory
{
    protected $model = FieldDefinition::class;

    public function definition(): array
    {
        return [
            'key' => fake()->slug(),
            'label' => fake()->words(3, true),
            'section' => fake()->word(),
            'sort_order' => fake()->numberBetween(1, 100),
            'is_required' => fake()->boolean(),
            'input_type' => 'text',
            'question_text' => fake()->sentence(),
            'help_text' => fake()->sentence(),
            'validation_rules' => null,
        ];
    }
}
