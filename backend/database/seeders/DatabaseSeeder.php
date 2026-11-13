<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan db:seed` (or `migrate --seed`) adds nothing: the Laravel
 * default created test@example.com with the password "password", a known
 * sign-in on a live server (pre-launch check). Plans and legal texts come
 * from the migrations; demo data from DemoSeeder (refused in production);
 * the first Super Admin as described in docs/deploy.md.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Nothing to seed. Demo data: php artisan db:seed --class=DemoSeeder (never on a live server).');
    }
}
