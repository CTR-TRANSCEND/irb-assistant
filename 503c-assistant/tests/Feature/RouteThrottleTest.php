<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_route_throttles_after_5_requests(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'user']);
        $project = Project::factory()->create(['owner_user_id' => $user->id]);

        $this->actingAs($user);

        // First 5 requests — may fail at the controller level, that is expected.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('projects.analyze', ['project' => $project->uuid]));
        }

        // The 6th request must be rejected by the throttle middleware.
        $response = $this->post(route('projects.analyze', ['project' => $project->uuid]));

        $response->assertStatus(429);
    }

    public function test_documents_upload_route_throttles_after_5_requests(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'user']);
        $project = Project::factory()->create(['owner_user_id' => $user->id]);

        $this->actingAs($user);

        // First 5 requests — invalid payload is fine; we test only the throttle.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('projects.documents.store', ['project' => $project->uuid]), []);
        }

        // The 6th request must be rejected by the throttle middleware.
        $response = $this->post(route('projects.documents.store', ['project' => $project->uuid]), []);

        $response->assertStatus(429);
    }
}
