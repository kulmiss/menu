<?php

use App\Enums\Role;
use Laravel\Sanctum\Sanctum;

// Laravel's plain "throttle:N,M" counts every route of one person (or one address)
// in ONE counter, so using one action used up the limit of another (QA, 2026-11-17):
// 10 menu-address checks while typing blocked the sign-up itself for a minute.

it('gives each route its own limit (guest)', function () {
    for ($i = 0; $i < 11; $i++) {
        $this->getJson('/api/v1/signup/slug?slug=cafe-'.$i)->assertSuccessful();
    }

    // sign-up allows 10 a minute, but none were used yet
    $this->postJson('/api/v1/signup', [], idem())->assertStatus(422);
});

it('gives each route its own limit (Super Admin)', function () {
    Sanctum::actingAs(superAdmin(), ['mobile', 'super']);
    for ($i = 0; $i < 7; $i++) {
        $this->getJson('/api/v1/super/search?q=caf')->assertOk();
    }

    // resend-welcome allows 6 a minute: an unknown restaurant still answers 404, not 429
    $this->postJson('/api/v1/super/restaurants/999999/resend-welcome', [], idem())->assertNotFound();
});

it('answers 404 for the wrong area before any limit (never 429)', function () {
    [$owner] = member(Role::Owner);
    Sanctum::actingAs($owner, ['mobile', 'restaurant']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/super/setup/demo/delete', [], idem())->assertNotFound();
        $this->postJson('/api/v1/super/settings/mail/test', [], idem())->assertNotFound();
    }
});
