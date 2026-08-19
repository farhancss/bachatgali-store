<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A single local admin so the panel is reachable straight after
 * `migrate:fresh --seed`. Real staff accounts are created in the panel.
 */
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@bachatgali.test'],
            [
                'name' => 'Store Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
