<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'owner_user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'status' => 'draft',
            'form_code' => 'hrp503c',
        ];
    }

    /** Outstanding #49 — multi-form rollout. */
    public function hrp503(): static
    {
        return $this->state(['form_code' => 'hrp503']);
    }

    public function hrp503c(): static
    {
        return $this->state(['form_code' => 'hrp503c']);
    }

    public function hrp398(): static
    {
        return $this->state(['form_code' => 'hrp398']);
    }
}
