<?php

namespace Database\Seeders;

use App\Console\Commands\DownloadDemoPhotos;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ImageProcessor;
use Database\Seeders\Demo\Cafe;
use Database\Seeders\Demo\Insights;
use Database\Seeders\Demo\Photos;
use Database\Seeders\Demo\Platform;
use Database\Seeders\Demo\Showcase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * The sales demo (never on a live server: refused when APP_ENV=production).
 *
 * - "Buna & Bread" at /demo: 67 dishes with real photos, 2 branches, team,
 *   tables, promotions, 90 days of insights.
 * - Three showcase restaurants, one per menu style: /demo-fine (A),
 *   /demo-pizza (B), /demo-bakery (C).
 * - About 30 small restaurants in every state with 12 months of payments,
 *   so every Super Admin widget has data.
 *
 * Real photos: run `php artisan demo:photos` once (or the PowerShell script
 * scripts/download-demo-photos.ps1); without them, simple generated pictures
 * are used. Credits: database/seeders/demo-photos/CREDITS.md.
 *
 * Sign in (password "password" for all): demo@example.com (owner),
 * manager@example.com, staff@example.com; fine@ / pizza@ / bakery@example.com;
 * admin@example.com + two-factor secret JBSWY3DPEHPK3PXP (Super Admin).
 *
 *   php artisan migrate:fresh --seed --seeder=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public const SLUG = Cafe::SLUG;

    public const ADMIN_2FA_SECRET = 'JBSWY3DPEHPK3PXP';

    /** Only the café (and the Super Admin): what CI's speed check and most tests need. */
    protected bool $cafeOnly = false;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('The demo data is never seeded on a live server (APP_ENV=production).');

            return;
        }
        if (Tenant::where('slug', self::SLUG)->exists()) {
            $this->command?->info('The demo restaurant already exists. Use migrate:fresh --seed --seeder=DemoSeeder to start over.');

            return;
        }
        // One Photos instance, so each photo is processed once and copied for the next restaurant.
        app()->instance(Photos::class, new Photos(app(ImageProcessor::class)));
        $missing = count(array_filter(DownloadDemoPhotos::manifest(), fn (array $p) => ! Photos::has($p['key'])));
        if ($missing > 0) {
            $this->command?->warn("{$missing} demo photos are not downloaded yet; generated pictures are used. Run: php artisan demo:photos");
        }

        $platform = app(Platform::class);
        $insights = app(Insights::class);
        $plans = $this->step('Plans and Super Admin', fn () => $platform->plans());
        $admin = $this->superAdmin();

        $cafe = $this->step('Buna & Bread: menus, photos, branches, team', fn () => app(Cafe::class)->seed($plans['business']));
        $this->step('Buna & Bread: 90 days of insights', fn () => $insights->seed($cafe, 90, 140));
        $paying = [$cafe];

        if (! $this->cafeOnly) {
            $showcases = $this->step('Showcase restaurants (A, B, C)', fn () => app(Showcase::class)->seed($plans));
            $this->step('Showcase restaurants: insights', function () use ($showcases, $insights) {
                foreach ($showcases as $i => $tenant) {
                    $insights->seed($tenant, 90, [60, 85, 45][$i]);
                }
            });
            $paying = [...$paying, ...$showcases];
            $this->step('30 small restaurants, payments, plan changes', fn () => $platform->smallRestaurants($plans, $admin));
            $platform->announcement($admin);
        }
        $this->step('Payments for the café and showcases', fn () => $platform->payFor($paying, $admin));
        $this->step('Invoices for every payment', fn () => Artisan::call('invoices:backfill'));
    }

    /**
     * Runs one part of the demo and prints a progress line with its time.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function step(string $label, callable $work): mixed
    {
        $this->command?->getOutput()->write("  {$label} … ");
        $started = microtime(true);
        $result = $work();
        $this->command?->getOutput()->writeln(sprintf('<info>done</info> (%.1f s)', microtime(true) - $started));

        return $result;
    }

    /** The demo Super Admin (two-factor on, as the platform requires). */
    private function superAdmin(): User
    {
        $admin = User::firstOrCreate(['email' => 'admin@example.com'], ['name' => 'Platform Admin', 'password' => 'password']);
        $admin->forceFill([
            'is_super_admin' => true,
            'email_verified_at' => now(),
            'two_factor_secret' => encrypt(self::ADMIN_2FA_SECRET),
            'two_factor_recovery_codes' => encrypt(json_encode(['demo-recovery-1', 'demo-recovery-2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $admin;
    }
}
