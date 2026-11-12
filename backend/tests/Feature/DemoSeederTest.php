<?php

use App\Console\Commands\DownloadDemoPhotos;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\BranchItemOverride;
use App\Models\Category;
use App\Models\Closure;
use App\Models\Item;
use App\Models\Membership;
use App\Models\Menu;
use App\Models\OpeningHour;
use App\Models\Payment;
use App\Models\PlatformAnnouncement;
use App\Models\Promotion;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Models\TenantProfile;
use App\Models\User;
use Database\Seeders\Demo\Photos;
use Database\Seeders\DemoCafeSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;

beforeEach(fn () => Storage::fake('public'));

it('seeds the complete sales demo: café, three showcases, small restaurants and platform data', function () {
    $this->seed(DemoSeeder::class);
    $cafe = Tenant::where('slug', DemoSeeder::SLUG)->sole();

    asTenant($cafe, function () {
        expect(Menu::count())->toBe(3)
            ->and(Menu::where('slug', 'breakfast')->sole()->schedule_mode)->toBe(Menu::SCHEDULED)
            ->and(Category::count())->toBe(10)
            ->and(Item::count())->toBeGreaterThanOrEqual(50)->toBeLessThanOrEqual(70)
            ->and(Item::whereNull('media_id')->count())->toBeBetween(3, 12)         // a few dishes show the photo-less look
            ->and(Item::has('photos', '>=', 2)->count())->toBeGreaterThanOrEqual(4) // galleries to swipe
            ->and(Item::where('is_sold_out', true)->count())->toBe(2)
            ->and(Item::where('is_new', true)->count())->toBeGreaterThan(3)
            ->and(Item::where('is_popular', true)->count())->toBeGreaterThan(5)
            ->and(Item::whereNotNull('calories')->whereNotNull('prep_minutes')->count())->toBeGreaterThan(20)
            ->and(Item::has('variants')->count())->toBeGreaterThan(5)
            ->and(Item::has('addonGroups')->count())->toBeGreaterThan(20)
            ->and(Item::has('allergenRows')->count())->toBeGreaterThan(30)
            ->and(Branch::pluck('slug')->sort()->values()->all())->toBe(['bole', 'piassa'])
            ->and(QrCode::whereNotNull('dining_table_id')->count())->toBe(18) // tables 1–12 and 1–6
            ->and(BranchItemOverride::where('is_sold_out', true)->count())->toBe(1)
            ->and(BranchItemOverride::whereNotNull('price_minor')->count())->toBeGreaterThan(3)
            ->and(Promotion::where('type', 'special')->whereNotNull('media_id')->count())->toBe(2)
            ->and(Promotion::where('type', 'special')->whereNotNull('item_id')->count())->toBe(1)
            ->and(Promotion::where('type', 'announcement')->count())->toBe(2)
            ->and(OpeningHour::where('weekday', 1)->count())->toBe(2)               // a break on weekdays
            ->and(Closure::count())->toBe(1);
        $profile = TenantProfile::sole();
        expect($profile->only(['address', 'maps_url', 'phone', 'whatsapp', 'telegram', 'email', 'wifi_name']))->each->not->toBeEmpty()
            ->and($profile->socials)->toHaveKeys(['instagram', 'facebook']);
        $look = TenantAppearance::sole();
        expect($look->cover_media_id)->not->toBeNull()->and($look->logo_media_id)->not->toBeNull();
    });
    expect(Membership::where('tenant_id', $cafe->id)->pluck('role')->map->value->sort()->values()->all())
        ->toBe([Role::Manager->value, Role::Owner->value, Role::Staff->value]);

    // 90 days of visits, scans, dish views and "My order" counts.
    $stats = DB::table('stats_daily')->where('tenant_id', $cafe->id);
    expect((clone $stats)->distinct()->count('date'))->toBe(90)
        ->and((clone $stats)->distinct()->pluck('metric')->sort()->values()->all())->toBe(['item_view', 'menu_view', 'order_add', 'order_shown', 'order_value', 'qr_scan']);
    $byDay = (clone $stats)->where('metric', 'menu_view')->where('date', '<', now()->toDateString())->groupBy('date')->pluck(DB::raw('SUM(count)'), 'date');
    $weekend = $byDay->filter(fn ($n, $d) => in_array(Carbon\Carbon::parse($d)->dayOfWeek, [0, 6], true))->avg();
    $weekday = $byDay->filter(fn ($n, $d) => in_array(Carbon\Carbon::parse($d)->dayOfWeek, [1, 2, 3], true))->avg();
    expect($weekend)->toBeGreaterThan($weekday * 1.2);

    // Showcases, one per style, with real-photo slots and owners.
    foreach (['demo-fine' => 'editorial', 'demo-pizza' => 'grid', 'demo-bakery' => 'bistro'] as $slug => $style) {
        $tenant = Tenant::where('slug', $slug)->sole();
        asTenant($tenant, fn () => expect(TenantAppearance::sole()->style)->toBe($style)->and(Item::count())->toBeGreaterThan(12)->and(Item::whereNotNull('media_id')->count())->toBeGreaterThan(7));
        $this->get('/'.$slug)->assertOk();
    }
    // Style D · Minimal on one demo restaurant, with banner and logo.
    $nine = Tenant::where('slug', 'demo-nine-wells-coffee')->sole();
    asTenant($nine, fn () => expect(TenantAppearance::sole())->style->toBe('minimal')->logo_media_id->not->toBeNull()->cover_media_id->not->toBeNull());
    $this->get('/demo-nine-wells-coffee')->assertOk()->assertSee('S-minimal', false);

    // Small restaurants in every state, payments over 12 months, plan changes this month.
    $states = Tenant::all()->countBy(fn (Tenant $t) => $t->subscriptionState()->value);
    expect(Tenant::count())->toBeGreaterThanOrEqual(30)
        ->and($states->keys()->all())->toContain('trial', 'active', 'grace', 'suspended');
    expect(Tenant::whereNotNull('paid_until')->whereBetween('paid_until', [now(), now()->addDays(7)])->count())->toBeGreaterThan(1)
        ->and(Payment::distinct()->count(DB::raw("DATE_FORMAT(paid_at, '%Y-%m')")))->toBeGreaterThanOrEqual(11)
        ->and(DB::table('activity_log')->where('action', 'platform.restaurant_updated')->where('created_at', '>=', now()->startOfMonth())->count())->toBeGreaterThan(1)
        ->and(PlatformAnnouncement::count())->toBe(1);
    expect(User::where('email', 'like', '%@example.com')->count())->toBe(User::count()); // only fictional e-mail addresses

    // Every photo the seeders use is in the credited list.
    $listed = collect(DownloadDemoPhotos::manifest())->pluck('key');
    expect(array_diff(array_keys(app(Photos::class)->used), $listed->all()))->toBe([]);

    $admin = User::where('email', 'admin@example.com')->sole();
    expect($admin->is_super_admin)->toBeTrue()
        ->and($admin->two_factor_confirmed_at)->not->toBeNull()
        ->and(decrypt($admin->two_factor_secret))->toBe(DemoSeeder::ADMIN_2FA_SECRET);
    expect(strlen((new Google2FA)->getCurrentOtp(DemoSeeder::ADMIN_2FA_SECRET)))->toBe(6);

    $this->get('/demo')->assertOk()->assertSee('class="top hc', false)->assertSee('Sunday buna ceremony')->assertSee('Info &amp; opening hours', false);
    $this->get('/demo/piassa')->assertOk()->assertSee('Piassa');
});

it('can seed only the café', function () {
    $this->seed(DemoCafeSeeder::class);

    expect(Tenant::pluck('slug')->all())->toBe([DemoSeeder::SLUG])
        ->and(User::where('email', 'admin@example.com')->exists())->toBeTrue();
});

it('never seeds demo data on a live server', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->seed(DemoSeeder::class);

    expect(Tenant::count())->toBe(0)->and(User::count())->toBe(0);
});
