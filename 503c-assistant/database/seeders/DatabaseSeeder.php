<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            Hrp503cFieldDefinitionSeeder::class,
            Hrp503FieldDefinitionSeeder::class,
            TemplateSeeder::class,
        ]);
    }
}
