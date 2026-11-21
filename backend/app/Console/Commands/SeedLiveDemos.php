<?php

namespace App\Console\Commands;

use App\Http\Controllers\SiteController;
use App\Models\Media;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ImageProcessor;
use App\Support\PublicMenu\MenuPageCache;
use App\Support\Qr\QrMap;
use App\Support\Setup\SetupStatus;
use App\Tenancy\CurrentTenant;
use Database\Seeders\Demo\Cafe;
use Database\Seeders\Demo\Photos;
use Database\Seeders\Demo\Showcase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The website's demo menus on a live server (owner, 2026-11-21, QUESTIONS #240): the café
 * (/demo) and one restaurant per menu style (the "More menu styles" row). Unlike DemoSeeder
 * (refused on a live server), it creates no Super Admin and no fake statistics, and every
 * demo account gets a long random password nobody knows. Demo restaurants keep @example.com
 * addresses, so Super Admin still sees them as demo data ("Delete demo restaurants").
 *
 *   php artisan demo:live            (asks first on a live server; --force does not ask)
 *   php artisan demo:live --refresh  (replaces the demo restaurants with the current menus)
 *
 * Safe to run again: restaurants that exist are left as they are, unless --refresh. --refresh
 * only removes demo restaurants (every member @example.com) at the demo addresses, with
 * their photos and menu pages, never a real restaurant.
 */
class SeedLiveDemos extends Command
{
    protected $signature = 'demo:live
        {--force : Do not ask on a live server}
        {--refresh : Delete the demo restaurants first, then make them again with the current menus}';

    protected $description = 'Create the website demo menus (café + one per style) with no usable logins';

    /** Demos with a short menu on purpose (owner, 2026-11-21: S · Injera & Tibs, "only a few dishes"); the others have 35 or more. */
    public const SHORT_MENUS = ['demo-shekla' => 12];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')
            && ! $this->confirm($this->option('refresh')
                ? 'Replace the demo restaurants on this live server? They are deleted and made again; their menus are public; nobody can sign in to them.'
                : 'Create the demo restaurants on this live server? Their menus are public; nobody can sign in to them.')) {
            return self::FAILURE;
        }
        $plans = Plan::whereIn('slug', ['free', 'plus', 'pro', 'business'])->get()->keyBy('slug')->all();
        if (count($plans) < 4) {
            $this->error('The plans are missing: run php artisan migrate --force first.');

            return self::FAILURE;
        }
        if ($this->option('refresh')) {
            $this->line(sprintf('%d demo restaurant(s) deleted.', $this->deleteDemos()));
        }
        // One Photos instance: each photo is processed once, then copied for the next restaurant.
        app()->instance(Photos::class, new Photos(app(ImageProcessor::class)));

        $made = [];
        if (! Tenant::withTrashed()->where('slug', SiteController::DEMO)->exists()) {
            $this->line('  '.SiteController::DEMO.' (café) …');
            $made[] = app(Cafe::class)->seed($plans['business']);
        }
        $showcase = app(Showcase::class);
        foreach (self::restaurants() as $def) {
            if (Tenant::withTrashed()->where('slug', $def['slug'])->exists()) {
                continue;
            }
            $this->line("  {$def['slug']} ({$def['style']}) …");
            $made[] = $showcase->restaurant($def, $plans);
        }

        $demos = Tenant::whereIn('slug', SiteController::demoSlugs())->get();
        DB::transaction(function () use ($demos) {
            foreach ($demos as $tenant) {
                // A sales demo, not a customer: it never runs out (the showcases start with 5 months).
                $tenant->forceFill(['paid_until' => now()->addYears(10)])->save();
                MenuPageCache::forget($tenant);
            }
            // No usable logins: a long random password nobody knows (the demo data uses "password").
            $users = Membership::whereIn('tenant_id', $demos->pluck('id'))->pluck('user_id');
            User::whereIn('id', $users)->where('email', 'like', '%@example.com')->get()
                ->each(fn (User $user) => $user->forceFill(['password' => Str::random(64), 'remember_token' => null])->save());
        });
        QrMap::touch();

        $this->info(sprintf('%d demo restaurant(s) created, %d in total.', count($made), $demos->count()));
        foreach ($demos->sortBy('slug') as $tenant) {
            $this->line('  '.url('/'.$tenant->slug));
        }

        return self::SUCCESS;
    }

    /**
     * The demo restaurants at the demo addresses, deleted for good (not the 30 days of a normal
     * delete, so the addresses are free again), with their photo files and menu pages.
     */
    private function deleteDemos(): int
    {
        $slugs = [SiteController::DEMO, ...array_column(self::restaurants(), 'slug')];
        $demos = SetupStatus::demo()->withTrashed()->whereIn('slug', $slugs)->get();
        foreach ($demos as $tenant) {
            app(CurrentTenant::class)->runAs($tenant, fn () => Media::all()->each(fn (Media $media) => $media->deleteFiles()));
            MenuPageCache::forget($tenant);
            $tenant->forceDelete();
        }

        return $demos->count();
    }

    /**
     * One restaurant per menu style, as on the website: the showcases (A, B, C, E–Q) and,
     * for style D, Nine Wells Coffee (in the full demo it is one of the small restaurants).
     * Style D shows no photos, so its dishes get none (nothing stored that is never shown).
     *
     * @return list<array<string, mixed>>
     */
    public static function restaurants(): array
    {
        $defs = Showcase::restaurants();
        $bakery = collect($defs)->firstWhere('slug', 'demo-bakery');
        $defs[] = [
            ...$bakery,
            'slug' => SiteController::STYLE_DEMOS['minimal'], 'name' => 'Nine Wells Coffee', 'email' => 'ninewells@example.com',
            'style' => 'minimal', 'color' => '#7c4a2d', 'banner_text' => 'under',
            'about' => "Single-origin coffee from Yirgacheffe and Sidamo, roasted every week.\nQuiet tables, good Wi-Fi, fresh pastries.",
            'address' => 'Bole, Addis Ababa', 'phone' => '+251 11 000 0477',
            'special' => ['Morning deal', 'Any coffee and a pastry for Br 150 before 10:00.', 'Croissant basket', 'juices-table'],
            'news' => ['New beans:', 'Washed Guji, fruity and bright.'],
        ];
        $minimal = array_key_last($defs);
        $defs[$minimal]['special'][3] = null;
        foreach ($defs[$minimal]['catalogue']['menus'] as $m => $menu) {
            foreach ($menu['categories'] as $c => $category) {
                foreach ($category[2] as $i => $dish) {
                    unset($defs[$minimal]['catalogue']['menus'][$m]['categories'][$c][2][$i][4]['ph']);
                }
            }
        }

        return $defs;
    }
}
