<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Support\PlatformSettings;
use App\Support\Security\SuperAdminAccess;

/*
 * The one-page sales sheet (owner, 2026-11-19, QUESTIONS #234): Super Admins
 * only, live plans and prices, the demo QR code, the contact details; A4.
 */
it('shows the sales sheet to a Super Admin with live prices, the demo QR code and the contacts', function () {
    Tenant::factory()->create(['slug' => 'demo']);
    PlatformSettings::save(['upgrade_whatsapp' => '+251 911 234 567', 'support_email' => 'hello@menu.test']);

    $html = $this->actingAs(superAdmin())->get(SuperAdminAccess::url('sales-sheet'))->assertOk()
        ->assertSee('Your menu on every phone.')
        ->assertSee('Plus')->assertSee('Br 490')        // the plan ladder from the database
        ->assertSee('+251911234567')->assertSee('hello@menu.test')
        ->assertSee('@page{size:A4;margin:0}', false)
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->getContent();
    expect($html)->toContain('<svg viewBox="0 0 ')->toContain('20 menu styles');
});

it('is not there for restaurant owners, guests or a Super Admin without two-factor', function () {
    [$owner] = member(Role::Owner);
    $url = SuperAdminAccess::url('sales-sheet');
    $this->actingAs($owner)->get($url)->assertNotFound();
    $this->actingAs(superAdmin(twoFactor: false))->get($url)->assertForbidden();
    auth()->logout();
    $this->get($url)->assertRedirect();
});
