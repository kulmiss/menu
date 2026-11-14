<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/** SECURITY_REPORT.md L7: "Sign out of all other browsers", keeping this one. */
it('signs out every other browser session but keeps this one, after the password', function () {
    $user = User::factory()->create(['email' => 'o@example.com', 'password' => 'correct-horse-battery']);
    // The admin SPA: a browser on this site, so Sanctum uses the session.
    $this->withHeader('Referer', 'http://localhost/admin');

    // Two browsers sign in.
    $this->postJson('/api/v1/auth/login', ['email' => 'o@example.com', 'password' => 'correct-horse-battery'], idem())->assertOk();
    $other = $this->app['session.store']->all();
    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->postJson('/api/v1/auth/login', ['email' => 'o@example.com', 'password' => 'correct-horse-battery'], idem())->assertOk();
    $this->getJson('/api/v1/me')->assertOk();

    $this->postJson('/api/v1/me/sessions/logout-others', ['password' => 'wrong-one'], idem())->assertInvalid('password');
    $this->postJson('/api/v1/me/sessions/logout-others', ['password' => 'correct-horse-battery'], idem())->assertOk();

    // This browser: still in, password unchanged.
    $this->getJson('/api/v1/me')->assertOk();
    $this->postJson('/api/v1/auth/logout', [], idem());
    $this->app['auth']->forgetGuards();

    // The other browser's session holds the old password hash: AuthenticateSession ends it.
    $this->flushSession();
    $this->withSession($other)->getJson('/api/v1/me')->assertUnauthorized();
    expect(Hash::check('correct-horse-battery', $user->fresh()->password))->toBeTrue();
});

it('is not available to the app (no browser session) or during "log in as"', function () {
    [$owner] = member(Role::Owner);
    $token = $owner->createToken('Phone', ['mobile'])->plainTextToken;
    $this->withToken($token)->postJson('/api/v1/me/sessions/logout-others', ['password' => 'password'], idem())->assertStatus(400);
});
