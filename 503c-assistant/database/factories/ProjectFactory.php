<?php

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
        ];
    }
}
