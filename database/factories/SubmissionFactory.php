<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FormDefinition;
use App\Models\Study;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    public function definition(): array
    {
        // Default to HRP-503c form definition (looked up at factory call time)
        $formDef = FormDefinition::where('form_code', 'HRP-503c')->first();

        return [
            'study_id' => Study::factory(),
            'form_definition_id' => $formDef?->id ?? 1,
            'user_id' => User::factory(),
            'status' => 'draft',
            'assistance_mode' => 'assistant',
            'title' => fake()->sentence(6),
            'principal_investigator' => fake()->name(),
            'oversight' => fake()->name().', '.fake()->jobTitle(),
        ];
    }

    /**
     * HRP-503 submission state.
     */
    public function hrp503(): static
    {
        return $this->state(function (array $attributes): array {
            $formDef = FormDefinition::where('form_code', 'HRP-503')->first();

            return ['form_definition_id' => $formDef?->id, 'status' => 'draft'];
        });
    }

    /**
     * HRP-503c submission state.
     */
    public function hrp503c(): static
    {
        return $this->state(function (array $attributes): array {
            $formDef = FormDefinition::where('form_code', 'HRP-503c')->first();

            return ['form_definition_id' => $formDef?->id, 'status' => 'draft'];
        });
    }

    /**
     * HRP-398 tracking_only submission state.
     * Per LD-9: HRP-398 submissions are always tracking_only.
     */
    public function hrp398(): static
    {
        return $this->state(function (array $attributes): array {
            $formDef = FormDefinition::where('form_code', 'HRP-398')->first();

            return ['form_definition_id' => $formDef?->id, 'status' => 'tracking_only'];
        });
    }
}
