<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;

class SettingsService
{
    /**
     * @var array<string, mixed>
     */
    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $setting = SystemSetting::query()->where('key', $key)->first();
        if ($setting === null) {
            return $default;
        }

        $value = $setting->value;
        $this->cache[$key] = $value;

        return $value ?? $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $val = $this->get($key, $default);

        if (is_bool($val)) {
            return $val;
        }

        if (is_int($val)) {
            return $val !== 0;
        }

        if (is_string($val)) {
            return filter_var($val, FILTER_VALIDATE_BOOLEAN);
        }

        return $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $val = $this->get($key, $default);

        if (is_int($val)) {
            return $val;
        }

        if (is_numeric($val)) {
            return (int) $val;
        }

        return $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $val = $this->get($key, $default);

        if (is_string($val)) {
            return $val;
        }

        return $default;
    }

    public function set(string $key, mixed $value, ?int $updatedByUserId = null): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'updated_by_user_id' => $updatedByUserId,
            ],
        );

        $this->cache[$key] = $value;
    }
}
