<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_user_role(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.update', ['user' => $user->id]), [
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.index', ['tab' => 'users']));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'admin',
        ]);
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.update', ['user' => $admin->id]), [
            'role' => 'admin',
            'is_active' => false,
        ]);

        $response->assertSessionHasErrors(['is_active']);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_remove_last_admin(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.update', ['user' => $admin->id]), [
            'role' => 'user',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors(['role']);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
        ]);
    }

    public function test_non_admin_cannot_update_users(): void
    {
        $regularUser = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        $target = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        $response = $this->actingAs($regularUser)->post(route('admin.users.update', ['user' => $target->id]), [
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response->assertForbidden();
    }
}
