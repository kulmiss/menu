<?php

use App\Models\Branch;
use App\Models\Item;
use App\Models\OpeningHour;
use App\Models\Promotion;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;

beforeEach(fn () => Storage::fake('public'));

it('seeds a complete demo café and a Super Admin with two-factor sign-in', function () {
    $this->seed(DemoSeeder::class);
    $tenant = Tenant::where('slug', DemoSeeder::SLUG)->sole();

    asTenant($tenant, function () {
        expect(Branch::count())->toBe(2)
            ->and(QrCode::count())->toBe(13) // 12 tables + the restaurant's own code
            ->and(Promotion::count())->toBe(2)
            ->and(OpeningHour::count())->toBe(7)
            ->and(Item::count())->toBeGreaterThan(50)
            ->and(Item::has('photos', '=', 3)->count())->toBe(3);
        $look = TenantAppearance::sole();
        expect($look->cover_media_id)->not->toBeNull()->and($look->logo_media_id)->not->toBeNull();
    });

    $admin = User::where('email', 'admin@example.com')->sole();
    expect($admin->is_super_admin)->toBeTrue()
        ->and($admin->two_factor_confirmed_at)->not->toBeNull()
        ->and(decrypt($admin->two_factor_secret))->toBe(DemoSeeder::ADMIN_2FA_SECRET);
    // The secret works with any authenticator app.
    expect(strlen((new Google2FA)->getCurrentOtp(DemoSeeder::ADMIN_2FA_SECRET)))->toBe(6);

    $this->get('/demo')->assertOk()->assertSee('class="top hc', false)->assertSee('Buna Monday')->assertSee('Info &amp; opening hours', false);
    $this->get('/demo/piassa')->assertOk()->assertSee('Piassa');
});

it('does not create the demo Super Admin on a live server', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->seed(DemoSeeder::class);

    expect(User::where('email', 'admin@example.com')->exists())->toBeFalse();
});
