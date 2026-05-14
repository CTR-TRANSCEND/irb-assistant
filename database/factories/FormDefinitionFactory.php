<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FormDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class FormDefinitionFactory extends Factory
{
    protected $model = FormDefinition::class;

    public function definition(): array
    {
        return [
            'form_code' => 'TEST-'.strtoupper(fake()->lexify('???')),
            'version' => '01.01.'.fake()->year(),
            'title' => fake()->sentence(4),
            'institution' => 'Sanford Health',
            'form_kind' => 'application',
            'description' => fake()->sentence(10),
            'instructions' => [],
            'is_fillable' => true,
            'is_retained' => true,
            'schema_json_path' => null,
            'is_active' => true,
        ];
    }

    public function guidanceWorksheet(): static
    {
        return $this->state([
            'form_kind' => 'guidance_worksheet',
            'is_fillable' => false,
            'is_retained' => false,
        ]);
    }
}
