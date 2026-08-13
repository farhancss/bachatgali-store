<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // A realistic demo catalog lands in phase 1 so local development and
        // staging both have something meaningful to browse.
        //
        // $this->call([
        //     CategorySeeder::class,
        //     BrandSeeder::class,
        //     ProductSeeder::class,
        //     DemoOrderSeeder::class,
        // ]);
    }
}
