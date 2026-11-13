<?php

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\PlatformTag;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\Invoices;

/** Super Admin global search (owner, 2026-11-13). */
it('finds restaurants, owners, invoices, payments and tags, grouped', function () {
    $this->actingAs(superAdmin());
    [$owner, $tenant] = member(Role::Owner);
    $tenant->update(['name' => 'Kaldi Corner', 'slug' => 'kaldi-corner']);
    $owner->update(['name' => 'Hana Kaldi', 'email' => 'hana@kaldi.et']);
    $payment = Payment::create(['tenant_id' => $tenant->id, 'amount_minor' => 50000, 'currency' => 'ETB', 'method' => 'bank', 'reference' => 'KALDI-TX-77', 'paid_at' => now()->toDateString(), 'period_start' => now()->toDateString(), 'period_end' => now()->addMonth()->toDateString()]);
    $invoice = app(Invoices::class)->issue($payment->load('tenant'));
    PlatformTag::create(['name' => 'Kaldi network']);

    $r = $this->getJson(p('search?q=kaldi'))->assertOk()->json('data');
    expect($r['restaurants'][0])->toMatchArray(['id' => $tenant->id, 'title' => 'Kaldi Corner', 'subtitle' => '/kaldi-corner'])
        ->and($r['owners'][0])->toMatchArray(['title' => 'Hana Kaldi', 'tenant_id' => $tenant->id])
        ->and($r['payments'][0]['title'])->toBe('KALDI-TX-77')
        ->and($r['tags'][0]['title'])->toBe('Kaldi network');
    $this->getJson(p('search?q='.urlencode($invoice->number)))->assertJsonPath('data.invoices.0.id', $invoice->id);
    $this->getJson(p('search?q=k'))->assertUnprocessable();
    $this->getJson(p('search?q=%25%25'))->assertOk()->assertJsonCount(0, 'data.restaurants'); // "%" is a letter, not a wildcard
});

it('is for Super Admins only', function () {
    [$owner] = member(Role::Owner);
    $this->actingAs($owner)->getJson(p('search?q=kaldi'))->assertNotFound();
    app('auth')->forgetGuards();
    $this->actingAs(superAdmin(twoFactor: false))->getJson(p('search?q=kaldi'))->assertForbidden();
});

it('answers in under 300 ms with 1,500 restaurants', function () {
    $this->actingAs(superAdmin());
    $now = now();
    foreach (array_chunk(range(1, 1500), 500) as $chunk) {
        Tenant::insert(array_map(fn ($i) => ['slug' => "place-{$i}", 'name' => "Place number {$i}", 'timezone' => 'Africa/Addis_Ababa', 'default_locale' => 'en', 'currency_code' => 'ETB', 'created_at' => $now, 'updated_at' => $now], $chunk));
        User::insert(array_map(fn ($i) => ['name' => "Owner {$i}", 'email' => "owner{$i}@example.com", 'password' => 'x', 'created_at' => $now, 'updated_at' => $now], $chunk));
    }
    $tenants = Tenant::where('slug', 'like', 'place-%')->pluck('id', 'slug');
    $users = User::where('email', 'like', 'owner%@example.com')->pluck('id', 'email');
    Membership::insert(collect(range(1, 1500))->map(fn ($i) => ['tenant_id' => $tenants["place-{$i}"], 'user_id' => $users["owner{$i}@example.com"], 'role' => 'owner', 'created_at' => $now, 'updated_at' => $now])->all());

    $this->getJson(p('search?q=number 14'))->assertOk(); // warm up
    $times = [];
    foreach (['number 1499', 'owner77', 'place-12', 'zzz-nothing'] as $q) {
        $start = hrtime(true);
        $this->getJson(p('search?q='.urlencode($q)))->assertOk();
        $times[] = (hrtime(true) - $start) / 1e6;
    }
    expect(max($times))->toBeLessThan(300);
    $this->getJson(p('search?q=number 1499'))->assertJsonPath('data.restaurants.0.title', 'Place number 1499');
});
