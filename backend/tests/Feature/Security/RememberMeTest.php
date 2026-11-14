<?php

use App\Models\User;

/** Owner's decision 2026-11-14: "remember me" lasts 90 days. */
it('sets a 90-day remember-me cookie at sign-in', function () {
    User::factory()->create(['email' => 'r@example.com', 'password' => 'correct-horse-battery']);

    $res = $this->postJson('/api/v1/auth/login', ['email' => 'r@example.com', 'password' => 'correct-horse-battery', 'remember' => true], idem())->assertOk();

    $cookie = collect($res->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
    expect($cookie)->not->toBeNull();
    $days = ($cookie->getExpiresTime() - time()) / 86400;
    expect($days)->toBeGreaterThan(89.9)->toBeLessThan(90.1);
});
