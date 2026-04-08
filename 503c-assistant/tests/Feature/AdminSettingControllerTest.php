<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_retention_days(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.store'), [
            'retention_days' => 30,
            'max_upload_bytes' => 104857600,
            'logging_level' => 'info',
        ]);

        $response->assertRedirect(route('admin.index', ['tab' => 'settings']));

        $setting = SystemSetting::query()->where('key', 'retention_days')->first();
        $this->assertNotNull($setting);
        $this->assertEquals(30, $setting->value);
    }

    public function test_validation_rejects_out_of_range_retention(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.store'), [
            'retention_days' => 0,
            'max_upload_bytes' => 104857600,
            'logging_level' => 'info',
        ]);

        $response->assertSessionHasErrors(['retention_days']);
    }

    public function test_admin_can_update_max_upload_bytes(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $maxUpload = 52428800; // 50 MB

        $response = $this->actingAs($admin)->post(route('admin.settings.store'), [
            'retention_days' => 14,
            'max_upload_bytes' => $maxUpload,
            'logging_level' => 'debug',
        ]);

        $response->assertRedirect(route('admin.index', ['tab' => 'settings']));

        $setting = SystemSetting::query()->where('key', 'max_upload_bytes')->first();
        $this->assertNotNull($setting);
        $this->assertEquals($maxUpload, $setting->value);
    }

    public function test_non_admin_cannot_update_settings(): void
    {
        $regularUser = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        $response = $this->actingAs($regularUser)->post(route('admin.settings.store'), [
            'retention_days' => 30,
            'max_upload_bytes' => 104857600,
            'logging_level' => 'info',
        ]);

        $response->assertForbidden();
    }
}
