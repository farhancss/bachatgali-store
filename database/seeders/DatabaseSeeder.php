<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // A realistic demo catalog so local development and staging both have
        // something meaningful to browse. Orders arrive with phase 3.
        $this->call([
            StaffSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
