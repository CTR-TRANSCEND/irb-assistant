<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Submission;
use App\Models\SubmissionAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubmissionAnswerFactory extends Factory
{
    protected $model = SubmissionAnswer::class;

    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'question_key' => 'q'.fake()->numberBetween(1, 43).'_'.fake()->numberBetween(1, 9),
            'text_value' => fake()->paragraph(),
            'option_value' => null,
            'bool_value' => null,
            'json_value' => null,
            'suggestion_source' => 'user',
        ];
    }

    public function withTextAnswer(string $text): static
    {
        return $this->state(['text_value' => $text, 'option_value' => null]);
    }

    public function withOptionAnswer(string $value): static
    {
        return $this->state(['option_value' => $value, 'text_value' => null]);
    }

    public function withBoolAnswer(bool $value): static
    {
        return $this->state(['bool_value' => $value, 'text_value' => null]);
    }

    public function assistantSuggested(): static
    {
        return $this->state(['suggestion_source' => 'assistant']);
    }
}
