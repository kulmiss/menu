<?php

use App\Console\Commands\SeedLiveDemos;
use App\Http\Controllers\SiteController;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Models\User;
use App\Support\Setup\SetupStatus;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/*
 * The website's demo menus on a live server (owner, 2026-11-21, QUESTIONS #240): the café and one
 * restaurant per menu style, with no usable logins, no Super Admin and no fake statistics.
 */

beforeEach(function () {
    Storage::fake('public');
    // Generated pictures instead of the real photos: the same code path, much faster.
    config(['menu.demo_photos_path' => sys_get_temp_dir().'/no-demo-photos-'.uniqid()]);
    app()['env'] = 'production';
});

it('creates the café and one demo restaurant per menu style on a live server, with no way to sign in', function () {
    $this->artisan('demo:live', ['--force' => true])->assertSuccessful()
        ->expectsOutputToContain('18 demo restaurant(s) created, 18 in total.');

    // Exactly the addresses the website links to: /demo and the "More menu styles" row.
    expect(Tenant::pluck('slug')->sort()->values()->all())->toBe(collect(SiteController::demoSlugs())->sort()->values()->all());
    foreach (SiteController::STYLE_DEMOS as $style => $slug) {
        $tenant = Tenant::where('slug', $slug)->sole();
        expect(app(CurrentTenant::class)->runAs($tenant, fn () => TenantAppearance::query()->value('style')))->toBe($style, $slug);
    }
    expect(TenantAppearance::STYLES)->toEqualCanonicalizing(array_keys(SiteController::STYLE_DEMOS));

    // No Super Admin, no known passwords, demo addresses only.
    expect(User::where('is_super_admin', true)->exists())->toBeFalse();
    foreach (User::all() as $user) {
        expect(Hash::check('password', $user->password))->toBeFalse($user->email)
            ->and($user->email)->toEndWith('@example.com');
    }
    expect(Auth::attempt(['email' => 'demo@example.com', 'password' => 'password']))->toBeFalse();
    // Super Admin still sees them as demo data (and can delete them in one go).
    expect(SetupStatus::demo()->count())->toBe(18);

    // A sales demo never runs out, and gets no invented visitors.
    expect(Tenant::where('paid_until', '<', now()->addYears(9))->exists())->toBeFalse()
        ->and(DB::table('stats_daily')->count())->toBe(0);

    // The menus open, style D included.
    $this->get('/demo-nine-wells-coffee')->assertOk()->assertSee('class="S-minimal', false)->assertSee('Nine Wells Coffee');
    $this->get('/demo')->assertOk();
    // And the website shows them.
    $this->get('/')->assertOk()->assertSee('/demo-firewood', false)->assertSee('/demo-nine-wells-coffee', false);
});

it('leaves existing demo restaurants alone when run again, and only adds what is missing', function () {
    $this->artisan('demo:live', ['--force' => true])->assertSuccessful();
    Tenant::where('slug', 'demo-juicy')->first()->forceDelete();
    $name = Tenant::where('slug', 'demo-fine')->value('name');
    Tenant::where('slug', 'demo-fine')->update(['name' => 'Renamed by the owner']);

    $this->artisan('demo:live', ['--force' => true])->assertSuccessful()
        ->expectsOutputToContain('1 demo restaurant(s) created, 18 in total.');
    expect(Tenant::where('slug', 'demo-juicy')->exists())->toBeTrue()
        ->and(Tenant::where('slug', 'demo-fine')->value('name'))->toBe('Renamed by the owner')->not->toBe($name);
});

it('asks first on a live server, and needs the plans', function () {
    $this->artisan('demo:live')->expectsConfirmation('Create the demo restaurants on this live server? Their menus are public; nobody can sign in to them.', 'no')->assertFailed();
    expect(Tenant::count())->toBe(0);

    DB::table('plans')->where('slug', 'pro')->delete();
    $this->artisan('demo:live', ['--force' => true])->assertFailed()->expectsOutputToContain('The plans are missing');
});

it('has one demo per style, each address once', function () {
    $slugs = array_column(SeedLiveDemos::restaurants(), 'slug');
    expect($slugs)->toHaveCount(count(TenantAppearance::STYLES))
        ->and(array_unique($slugs))->toHaveCount(count($slugs))
        ->and($slugs)->toEqualCanonicalizing(array_values(SiteController::STYLE_DEMOS));
});
