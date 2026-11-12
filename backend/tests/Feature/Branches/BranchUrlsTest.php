<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\QrCode;
use App\Models\TenantAppearance;

/** One URL and QR code per branch, no picker (owner, 2026-11-12). */
it('shows each branch only its own menu, with no branch picker', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $this->actingAs($owner)->postJson(t($tenant, 'branches'), ['name' => ['en' => 'Piassa'], 'slug' => 'piassa', 'address' => ['en' => 'Churchill Ave 12']], idem())->assertCreated();
    $main = asTenant($tenant, fn () => Branch::where('is_default', true)->firstOrFail());

    $piassa = $this->get('/'.$tenant->slug.'/piassa')->assertOk()->getContent();
    $home = $this->get('/'.$tenant->slug)->assertOk()->getContent();

    foreach ([$piassa, $home] as $html) {
        expect($html)->not->toContain('class="ch bs"')->not->toContain('aria-current="page"');
    }
    // The branch name sits quietly under the restaurant name, main branch included.
    expect($piassa)->toContain('<p class="sub">Piassa</p>')
        ->and($home)->toContain('<p class="sub">'.$main->name['en'].'</p>');
    // "Other branches" lists the rest, with addresses, never the branch you are on.
    expect($piassa)->toContain('Other branches')->toContain('<a href="/'.$tenant->slug.'">'.$main->name['en'].'</a>')
        ->not->toContain('<a href="/'.$tenant->slug.'/piassa">')
        ->and($home)->toContain('<a href="/'.$tenant->slug.'/piassa">Piassa</a><span>Churchill Ave 12</span>');

    // The restaurant can switch the list off.
    asTenant($tenant, fn () => TenantAppearance::current()->fill(['show_other_branches' => false])->save());
    expect($this->get('/'.$tenant->slug.'/piassa')->getContent())->not->toContain('Other branches');
});

it('shows no branch name or list for a restaurant with one branch', function () {
    [, $tenant] = member(Role::Owner);
    sampleMenu($tenant);

    expect($this->get('/'.$tenant->slug)->assertOk()->getContent())
        ->not->toContain('class="sub"')->not->toContain('Other branches');
});

it('tells the admin which branch every QR code opens', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $id = $this->actingAs($owner)->postJson(t($tenant, 'branches'), ['name' => ['en' => 'Piassa'], 'slug' => 'piassa'], idem())->json('data.id');
    $this->actingAs($owner)->postJson(t($tenant, "branches/{$id}/tables"), ['label' => '4'], idem())->assertSuccessful();
    $table = asTenant($tenant, fn () => DiningTable::where('branch_id', $id)->firstOrFail());
    $mainName = asTenant($tenant, fn () => Branch::where('is_default', true)->firstOrFail()->name['en']);

    $rows = collect($this->actingAs($owner)->getJson(t($tenant, 'qr-codes'))->assertOk()->json('data'));

    expect($rows->firstWhere('kind', 'restaurant')['branch'])->toBe($mainName)
        ->and($rows->firstWhere('kind', 'branch')['branch'])->toBe('Piassa');
    $tableRow = $rows->firstWhere('dining_table_id', $table->id);
    expect($tableRow['branch'])->toBe('Piassa')->and($tableRow['branch_id'])->toBe($id);
    // Table QR codes always belong to a branch.
    expect(asTenant($tenant, fn () => QrCode::whereNotNull('dining_table_id')->get()->every(fn (QrCode $q) => $q->table->branch_id !== null)))->toBeTrue();
});
