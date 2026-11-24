<?php

use App\Enums\Role;
use App\Models\QrCode;
use App\Support\Features;
use App\Support\Qr\QrMap;

/*
 * Extra QR codes (flyer, poster, window) can be switched off (owner, 2026-11-24). Off: no
 * "Extra code" box in the admin (the POST is refused), and existing extra codes are hidden
 * from the QR list; a code already printed keeps opening the menu.
 */

/** A restaurant with the main QR code and one extra code ("Window poster"). */
function extraCodesRestaurant(): array
{
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    test()->actingAs($owner)->postJson(t($tenant, 'qr-codes'), ['label' => 'Window poster'], idem())->assertCreated();
    $code = asTenant($tenant, fn () => QrCode::whereNotNull('label')->value('code'));

    return [$owner, $tenant->fresh(), $code];
}

it('is on by default: the extra code shows and a new one can be added', function () {
    [$owner, $tenant] = extraCodesRestaurant();
    expect(Features::DEFAULTS['extra_codes'])->toBeTrue();
    $rows = collect($this->actingAs($owner)->getJson(t($tenant, 'qr-codes'))->assertOk()->json('data'));
    expect($rows->where('kind', 'extra'))->toHaveCount(1)
        ->and($rows->where('kind', 'restaurant'))->toHaveCount(1);
    $this->actingAs($owner)->postJson(t($tenant, 'qr-codes'), ['label' => 'Flyer'], idem())->assertCreated();
});

it('switched off: the box is refused, extra codes are hidden, printed ones still work', function () {
    [$owner, $tenant, $code] = extraCodesRestaurant();
    $this->actingAs(superAdmin())->putJson(p('settings/features'), ['features' => ['extra_codes' => false]], idem())->assertOk();
    Features::forget();

    $this->actingAs($owner);
    // No new extra codes.
    $this->postJson(t($tenant, 'qr-codes'), ['label' => 'Flyer'], idem())->assertStatus(402)->assertJsonPath('code', 'features.extra_codes');
    // The list keeps the restaurant code, without the extra one.
    $kinds = collect($this->getJson(t($tenant, 'qr-codes'))->assertOk()->json('data'))->pluck('kind')->all();
    expect($kinds)->toContain('restaurant')->not->toContain('extra');
    // Nothing was deleted: the extra code is still there.
    expect(asTenant($tenant, fn () => QrCode::whereNotNull('label')->count()))->toBe(1);
    // The printed code still opens the menu.
    $this->app['auth']->forgetGuards();
    expect(QrMap::entries())->toHaveKey($code);
    $this->get('/q/'.$code)->assertRedirect();

    // On again: the extra code is back.
    $this->actingAs(superAdmin())->putJson(p('settings/features'), ['features' => ['extra_codes' => true]], idem())->assertOk();
    Features::forget();
    expect(collect($this->actingAs($owner)->getJson(t($tenant, 'qr-codes'))->json('data'))->where('kind', 'extra'))->toHaveCount(1);
});

it('leaves table codes in the list when only extra codes are off', function () {
    [$owner, $tenant, $code] = extraCodesRestaurant();
    $this->actingAs(superAdmin())->putJson(p('settings/features'), ['features' => ['extra_codes' => false]], idem())->assertOk();
    Features::forget();
    // A branch code (label null) is kept; only the extra code (label set) is hidden.
    $kinds = collect($this->actingAs($owner)->getJson(t($tenant, 'qr-codes'))->json('data'))->pluck('kind')->all();
    expect($kinds)->toContain('restaurant')->not->toContain('extra');
});
