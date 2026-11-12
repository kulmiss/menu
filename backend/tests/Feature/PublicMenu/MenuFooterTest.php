<?php

use App\Enums\Role;
use App\Models\TenantAppearance;

/** The guest page footer (owner, 2026-11-12). */
it('shows name, grouped hours, address with maps, contact and social icons in the footer', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $this->actingAs($owner)->putJson(t($tenant, 'profile'), [
        'address' => ['en' => 'Bole Road, Addis Ababa'],
        'maps_url' => 'https://maps.app.goo.gl/abc',
        'phone' => '+251 11 555 0100',
        'whatsapp' => '+251 91 555 0100',
        'telegram' => '@bunabread',
        'socials' => ['instagram' => 'https://instagram.com/buna', 'tiktok' => 'https://tiktok.com/@buna'],
        'wifi_name' => 'Buna-Guest', 'wifi_password' => 'coffee123', 'show_wifi' => true,
    ], idem())->assertOk();
    $weekdays = array_map(fn ($d) => ['weekday' => $d, 'opens_at' => '07:00', 'closes_at' => '22:00'], [1, 2, 3, 4, 5]);
    $this->actingAs($owner)->putJson(t($tenant, 'hours'), ['hours' => [...$weekdays, ['weekday' => 6, 'opens_at' => '08:00', 'closes_at' => '23:00']]], idem())->assertOk();

    $html = $this->get('/'.$tenant->slug)->assertOk()->getContent();
    $footer = substr($html, strpos($html, '<footer class="ft" id="info">'), strpos($html, '</footer>') - strpos($html, '<footer class="ft" id="info">'));

    expect($footer)->toContain('<p class="fn">'.e($tenant->name).'</p>')
        // Days in a row with the same hours share a line.
        ->toContain('<dt>Mon–Fri</dt><dd>07:00–22:00</dd>')->toContain('<dt>Sat</dt><dd>08:00–23:00</dd>')->toContain('<dt>Sun</dt><dd>Closed</dd>')
        ->toContain('Bole Road, Addis Ababa')->toContain('<a class="fbt" href="https://maps.app.goo.gl/abc" rel="noopener" target="_blank">Open in Maps</a>')
        ->toContain('href="tel:+251115550100"')->toContain('href="https://wa.me/251915550100"')->toContain('href="https://t.me/bunabread"')
        ->toContain('aria-label="Instagram"')->toContain('aria-label="TikTok"')
        ->toContain('Buna-Guest')
        ->and(substr_count($footer, '<svg aria-hidden="true"'))->toBeGreaterThanOrEqual(8);
    // No old collapsible info box any more; the (i) button still points at the footer.
    expect($html)->not->toContain('<details')->toContain('href="#info"');
});

it('shows the PDF link whenever it is on, and "Menu by" only when the plan shows branding', function () {
    [, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    asTenant($tenant, fn () => TenantAppearance::current()->fill(['show_pdf_link' => true])->save());

    expect($this->get('/'.$tenant->slug)->getContent())->toContain('/menu.pdf"')->toContain('Menu by');

    // A plan without branding used to hide the PDF link too (it sat inside the branding block).
    $this->actingAs(superAdmin())->putJson(p("restaurants/{$tenant->id}/features"), ['features' => ['remove_branding' => true]], idem())->assertOk();
    expect($this->get('/'.$tenant->slug)->getContent())->toContain('/menu.pdf"')->not->toContain('Menu by');
});

it('keeps "Nothing matches your search" hidden until a search finds nothing', function () {
    [, $tenant] = member(Role::Owner);
    sampleMenu($tenant);

    expect($this->get('/'.$tenant->slug)->getContent())
        ->toContain('<p class="nr" id="nr" hidden>')
        ->toContain('.nr[hidden]{display:none}');
});
