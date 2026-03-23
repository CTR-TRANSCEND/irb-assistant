<?php

namespace Database\Seeders;

use App\Services\TemplateService;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(TemplateService $templates): void
    {
        $templates->ensureDefaultTemplateInstalled();
    }
}
