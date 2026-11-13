<?php

namespace Database\Seeders;

/**
 * Only "Buna & Bread" (with its insights) and the Super Admin — quicker, for
 * tests and for a laptop that only needs the café:
 *
 *   php artisan db:seed --class=DemoCafeSeeder
 */
class DemoCafeSeeder extends DemoSeeder
{
    protected bool $cafeOnly = true;
}
