<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = (string) env('ADMIN_NAME', 'Admin');
        $email = (string) env('ADMIN_EMAIL', 'admin@example.com');
        $password = (string) env('ADMIN_PASSWORD', 'change-me');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
            ],
        );
    }
}
