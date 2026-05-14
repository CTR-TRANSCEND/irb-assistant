<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Study;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StudyFactory extends Factory
{
    protected $model = Study::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'application_title' => fake()->sentence(6),
            'pi_name' => fake()->name(),
            'project_summary' => fake()->paragraph(),
            'oversight' => fake()->name().', '.fake()->jobTitle(),
            'nickname' => implode(' ', fake()->words(3)),
        ];
    }

    /**
     * Study with all 3 auto-created Submissions.
     *
     * Note: Study::create() already triggers the auto-create hook via the 'created' event.
     * This state is a no-op marker — the 3 submissions are automatically created on
     * Study::create() by the boot() listener in the Study model.
     */
    public function withAllThreeSubmissions(): static
    {
        // Auto-create runs on Study::create() via the boot hook.
        // No additional state needed — submissions are created automatically.
        return $this;
    }
}
