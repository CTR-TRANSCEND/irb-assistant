<?php

namespace Database\Seeders;

use App\Services\TemplateService;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(TemplateService $templates): void
    {
        // Multi-form rollout (Outstanding #49) — install all 3 bundled templates
        // (HRP-503, HRP-503c, HRP-398) so per-project routing has something to
        // pick from. Idempotent: re-running this seeder is safe.
        $templates->ensureAllTemplatesInstalled();
    }
}
