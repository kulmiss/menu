<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Support\Qr\QrMap;
use chillerlan\QRCode\QRCode as Reader;

function branchPayload(array $extra = []): array
{
    return ['name' => ['en' => 'Piassa'], 'slug' => 'piassa', ...$extra];
}

it('gives every new restaurant a main branch and a QR code that opens its menu', function () {
    $tenant = Tenant::factory()->create(['slug' => 'buna']);
    sampleMenu($tenant);
    $code = asTenant($tenant, fn () => QrCode::firstOrFail());

    expect(asTenant($tenant, fn () => Branch::where('is_default', true)->count()))->toBe(1)
        ->and(QrMap::entries()[$code->code])->toBe('/buna#q='.$code->code);

    QrMap::rebuild();
    expect((include QrMap::path())[$code->code])->toBe('/buna#q='.$code->code);

    // Laravel answers too (servers without the rewrite rule), upper or lower case.
    $this->get('/q/'.$code->code)->assertRedirect('/buna#q='.$code->code)->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/q/'.strtolower($code->code))->assertRedirect('/buna#q='.$code->code);
    $this->get('/q/ZZZZZZZZ')->assertNotFound();
});

it('creates branches with their own QR code, within the plan limit', function () {
    [$owner, $tenant] = member(Role::Owner);

    $this->actingAs($owner)->postJson(t($tenant, 'branches'), branchPayload(), idem())->assertCreated()
        ->assertJsonPath('data.slug', 'piassa')
        ->assertJsonPath('data.is_default', false)
        ->assertJsonPath('data.url', url('/'.$tenant->slug.'/piassa'));
    $code = asTenant($tenant, fn () => QrCode::whereNotNull('branch_id')->whereNull('dining_table_id')->whereHas('branch', fn ($q) => $q->where('slug', 'piassa'))->firstOrFail());
    expect(QrMap::entries()[$code->code])->toBe('/'.$tenant->slug.'/piassa#q='.$code->code);

    $this->actingAs($owner)->postJson(t($tenant, 'branches'), branchPayload(), idem())->assertInvalid('slug');

    $plan = Plan::create(['name' => ['en' => 'Starter'], 'slug' => 'starter', 'limits' => ['branches' => 2]]);
    $tenant->forceFill(['plan_id' => $plan->id])->save();
    $this->actingAs($owner)->postJson(t($tenant, 'branches'), branchPayload(['slug' => 'bole']), idem())
        ->assertStatus(402)->assertJsonPath('code', 'limits.branches');
});

it('moves the main branch, keeps it active and never deletes it', function () {
    [$owner, $tenant] = member(Role::Owner);
    $main = asTenant($tenant, fn () => Branch::where('is_default', true)->firstOrFail());
    $id = $this->actingAs($owner)->postJson(t($tenant, 'branches'), branchPayload(), idem())->json('data.id');

    $this->actingAs($owner)->deleteJson(t($tenant, "branches/{$main->id}"), [], idem())->assertInvalid('branch');
    $this->actingAs($owner)->patchJson(t($tenant, "branches/{$main->id}"), ['is_active' => false], idem())->assertInvalid('is_active');

    $this->actingAs($owner)->patchJson(t($tenant, "branches/{$id}"), ['is_default' => true], idem())->assertOk();
    expect(asTenant($tenant, fn () => Branch::where('is_default', true)->pluck('id')->all()))->toBe([$id]);

    $this->actingAs($owner)->deleteJson(t($tenant, "branches/{$main->id}"), [], idem())->assertNoContent();
});

it('adds numbered tables, each with a code, skipping ones that exist', function () {
    [$owner, $tenant] = member(Role::Owner);
    $main = asTenant($tenant, fn () => Branch::firstOrFail());

    $this->actingAs($owner)->postJson(t($tenant, "branches/{$main->id}/tables"), ['prefix' => 'T', 'from' => 1, 'to' => 3], idem())
        ->assertCreated()->assertJsonCount(3, 'data')->assertJsonPath('data.0.label', 'T 1');
    $this->actingAs($owner)->postJson(t($tenant, "branches/{$main->id}/tables"), ['prefix' => 'T', 'from' => 3, 'to' => 4], idem())
        ->assertCreated()->assertJsonCount(1, 'data')->assertJsonPath('data.0.label', 'T 4');

    $tables = $this->actingAs($owner)->getJson(t($tenant, "branches/{$main->id}/tables"))->assertOk()->assertJsonCount(4, 'data')->json('data');
    $first = $tables[0];
    expect(QrMap::entries()[$first['qr_code']])->toBe('/'.$tenant->slug.'#q='.$first['qr_code']);

    // A deleted table's code stops working and is never handed out again.
    $this->actingAs($owner)->deleteJson(t($tenant, "tables/{$first['id']}"), [], idem())->assertNoContent();
    expect(QrMap::entries())->not->toHaveKey($first['qr_code'])
        ->and(QrCode::withoutGlobalScopes()->withTrashed()->where('code', $first['qr_code'])->exists())->toBeTrue();
    $this->get('/q/'.$first['qr_code'])->assertNotFound();
});

it('serves each branch its own menu: overrides, limited menus, address', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$menu, $category, $item] = sampleMenu($tenant);
    $branchId = $this->actingAs($owner)->postJson(t($tenant, 'branches'), branchPayload(), idem())->json('data.id');
    $hidden = asTenant($tenant, fn () => Item::create(['category_id' => $category->id, 'name' => ['en' => 'Shai'], 'price_minor' => 4500]));
    $drinks = asTenant($tenant, function () {
        $m = Menu::create(['name' => ['en' => 'Bar'], 'slug' => 'bar']);
        $c = Category::create(['menu_id' => $m->id, 'name' => ['en' => 'Beer']]);
        Item::create(['category_id' => $c->id, 'name' => ['en' => 'Habesha beer'], 'price_minor' => 9000]);

        return $m;
    });

    $this->actingAs($owner)->putJson(t($tenant, "branches/{$branchId}/overrides"), ['overrides' => [
        ['item_id' => $item->id, 'price_minor' => 15000, 'is_sold_out' => true, 'is_hidden' => null],
        ['item_id' => $hidden->id, 'price_minor' => null, 'is_sold_out' => null, 'is_hidden' => true],
    ]], idem())->assertOk()->assertJsonCount(2, 'data');
    $this->actingAs($owner)->putJson(t($tenant, "menus/{$drinks->id}/branches"), ['branch_ids' => [$branchId]], idem())
        ->assertOk()->assertJsonPath('data.branch_ids', [$branchId]);

    $this->get('/'.$tenant->slug.'/piassa')->assertOk()
        ->assertSee('Piassa')->assertSee('Br 150')->assertSee('class="so"', false)->assertDontSee('Shai')->assertSee('Habesha beer')
        ->assertSee('href="/'.$tenant->slug.'"', false);
    $this->get('/'.$tenant->slug)->assertOk()
        ->assertSee('Br 120')->assertSee('Shai')->assertDontSee('Habesha beer')->assertDontSee('class="so"', false);

    $this->get('/'.$tenant->slug.'/main')->assertRedirect('/'.$tenant->slug);
    $this->get('/'.$tenant->slug.'/nowhere')->assertNotFound();
    $this->actingAs($owner)->patchJson(t($tenant, "branches/{$branchId}"), ['is_active' => false], idem())->assertOk();
    $this->get('/'.$tenant->slug.'/piassa')->assertNotFound();
});

it('downloads codes that scan back to the right address', function () {
    [$owner, $tenant] = member(Role::Owner);
    // A fixed code keeps the test deterministic (the in-PHP reader is stricter
    // than phone scanners; 400 random codes were checked with zxing-cpp).
    $code = asTenant($tenant, fn () => tap(QrCode::firstOrFail())->update(['code' => 'K7PM3XQ9']));
    $url = url('/q/K7PM3XQ9');

    $png = $this->actingAs($owner)->get(t($tenant, "qr-codes/{$code->id}.png"))->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();
    expect((new Reader)->readFromBlob((string) $png)->data)->toBe($url);

    $svg = $this->actingAs($owner)->get(t($tenant, "qr-codes/{$code->id}.svg"))->assertOk()->getContent();
    expect($svg)->toContain('<svg');

    $pdf = $this->actingAs($owner)->get(t($tenant, "qr-codes/{$code->id}.pdf"))->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
    expect(substr((string) $pdf, 0, 5))->toBe('%PDF-');
});

it('prints table cards for a branch, four per page', function () {
    [$owner, $tenant] = member(Role::Owner);
    $main = asTenant($tenant, fn () => Branch::firstOrFail());
    $this->actingAs($owner)->postJson(t($tenant, "branches/{$main->id}/tables"), ['prefix' => 'Table', 'from' => 1, 'to' => 6], idem())->assertCreated();

    $pdf = (string) $this->actingAs($owner)->get(t($tenant, "branches/{$main->id}/table-cards.pdf"))->assertOk()->getContent();

    expect(substr($pdf, 0, 5))->toBe('%PDF-')
        ->and(preg_match_all('#/Type /Page\b#', $pdf))->toBe(2);
});

it('lists codes and manages extra ones', function () {
    [$owner, $tenant] = member(Role::Owner);

    $id = $this->actingAs($owner)->postJson(t($tenant, 'qr-codes'), ['label' => 'Window poster'], idem())
        ->assertCreated()->assertJsonPath('data.kind', 'extra')->json('data.id');
    $this->actingAs($owner)->getJson(t($tenant, 'qr-codes'))->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.kind', 'restaurant');

    $restaurant = asTenant($tenant, fn () => QrCode::whereNull('label')->firstOrFail());
    $this->actingAs($owner)->deleteJson(t($tenant, "qr-codes/{$restaurant->id}"), [], idem())->assertStatus(422);
    $this->actingAs($owner)->deleteJson(t($tenant, "qr-codes/{$id}"), [], idem())->assertNoContent();
});

it('keeps branches, tables and codes to the right people and restaurant', function () {
    [$staff, $tenant] = member(Role::Staff);
    [$owner] = member(Role::Owner);
    $other = Tenant::factory()->create();
    $foreign = asTenant($other, fn () => Branch::firstOrFail());
    $foreignCode = asTenant($other, fn () => QrCode::firstOrFail());

    $this->actingAs($staff)->getJson(t($tenant, 'branches'))->assertForbidden();
    $this->actingAs($staff)->getJson(t($tenant, 'qr-codes'))->assertForbidden();

    $ownTenant = $owner->memberships()->first()->tenant;
    $this->actingAs($owner)->patchJson(t($ownTenant, "branches/{$foreign->id}"), ['name' => ['en' => 'X']], idem())->assertNotFound();
    $this->actingAs($owner)->get(t($ownTenant, "qr-codes/{$foreignCode->id}.png"))->assertNotFound();
    $this->actingAs($owner)->postJson(t($ownTenant, "branches/{$foreign->id}/tables"), ['label' => '1'], idem())->assertNotFound();
});
