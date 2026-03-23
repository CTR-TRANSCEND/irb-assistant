<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SystemSetting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): SettingsService
    {
        return new SettingsService;
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function test_get_returns_default_when_key_not_found(): void
    {
        $service = $this->makeService();

        $this->assertNull($service->get('nonexistent'));
        $this->assertSame('fallback', $service->get('nonexistent', 'fallback'));
    }

    public function test_get_returns_stored_value(): void
    {
        SystemSetting::create(['key' => 'site_name', 'value' => 'My App']);

        $service = $this->makeService();

        $this->assertSame('My App', $service->get('site_name'));
    }

    public function test_get_caches_value_on_second_call(): void
    {
        SystemSetting::create(['key' => 'cached_key', 'value' => 'original']);

        $service = $this->makeService();

        // First call hits the database and populates the cache.
        $first = $service->get('cached_key');

        // Mutate the row directly to confirm the second call does not re-query.
        SystemSetting::where('key', 'cached_key')->update(['value' => json_encode('mutated')]);

        // Second call must return the cached value, not the mutated one.
        $second = $service->get('cached_key');

        $this->assertSame('original', $first);
        $this->assertSame('original', $second);
    }

    // -------------------------------------------------------------------------
    // bool()
    // -------------------------------------------------------------------------

    /**
     * @dataProvider truthyStringProvider
     */
    public function test_bool_returns_true_for_truthy_string_values(string $value): void
    {
        SystemSetting::create(['key' => 'flag', 'value' => $value]);

        $service = $this->makeService();

        $this->assertTrue($service->bool('flag'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function truthyStringProvider(): array
    {
        return [
            "'1'"    => ['1'],
            "'true'" => ['true'],
            "'yes'"  => ['yes'],
            "'on'"   => ['on'],
        ];
    }

    /**
     * @dataProvider falsyStringProvider
     */
    public function test_bool_returns_false_for_falsy_string_values(string $value): void
    {
        SystemSetting::create(['key' => 'flag', 'value' => $value]);

        $service = $this->makeService();

        $this->assertFalse($service->bool('flag'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function falsyStringProvider(): array
    {
        return [
            "'0'"     => ['0'],
            "'false'" => ['false'],
            "'no'"    => ['no'],
            "'off'"   => ['off'],
            "''"      => [''],
        ];
    }

    public function test_bool_returns_default_for_non_boolean_types(): void
    {
        // An array value is not a bool, int, or string — must fall back to default.
        SystemSetting::create(['key' => 'complex', 'value' => ['nested' => 'array']]);

        $service = $this->makeService();

        $this->assertFalse($service->bool('complex', false));
        // When key is entirely absent the default is also returned.
        $this->assertTrue($service->bool('missing_key', true));
    }

    // -------------------------------------------------------------------------
    // int()
    // -------------------------------------------------------------------------

    public function test_int_returns_integer_for_numeric_string(): void
    {
        SystemSetting::create(['key' => 'timeout', 'value' => '42']);

        $service = $this->makeService();

        $this->assertSame(42, $service->int('timeout'));
    }

    public function test_int_returns_default_for_non_numeric(): void
    {
        SystemSetting::create(['key' => 'bad_int', 'value' => 'not-a-number']);

        $service = $this->makeService();

        $this->assertSame(0, $service->int('bad_int'));
        $this->assertSame(99, $service->int('missing_int_key', 99));
    }

    // -------------------------------------------------------------------------
    // string()
    // -------------------------------------------------------------------------

    public function test_string_returns_stored_string(): void
    {
        SystemSetting::create(['key' => 'greeting', 'value' => 'Hello, World!']);

        $service = $this->makeService();

        $this->assertSame('Hello, World!', $service->string('greeting'));
    }

    public function test_string_returns_default_for_non_string(): void
    {
        // Integers stored via JSON come back as int, not string.
        SystemSetting::create(['key' => 'num', 'value' => 123]);

        $service = $this->makeService();

        $this->assertSame('', $service->string('num'));
        $this->assertSame('default', $service->string('missing_string_key', 'default'));
    }

    // -------------------------------------------------------------------------
    // set()
    // -------------------------------------------------------------------------

    public function test_set_creates_new_setting(): void
    {
        $service = $this->makeService();
        $service->set('new_key', 'new_value');

        $this->assertDatabaseHas('system_settings', ['key' => 'new_key']);

        $row = SystemSetting::where('key', 'new_key')->first();
        $this->assertNotNull($row);
        $this->assertSame('new_value', $row->value);
        $this->assertNull($row->updated_by_user_id);
    }

    public function test_set_updates_existing_setting(): void
    {
        SystemSetting::create(['key' => 'existing_key', 'value' => 'old_value']);

        $service = $this->makeService();
        $service->set('existing_key', 'updated_value');

        // Only one row should exist for this key.
        $this->assertSame(1, SystemSetting::where('key', 'existing_key')->count());

        $row = SystemSetting::where('key', 'existing_key')->first();
        $this->assertNotNull($row);
        $this->assertSame('updated_value', $row->value);
    }
}
