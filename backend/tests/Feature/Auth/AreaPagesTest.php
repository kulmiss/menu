<?php

use App\Enums\Role;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PragmaRX\Google2FA\Google2FA;

/**
 * The two areas on the web (QUESTIONS #215–#222): their own addresses and
 * sign-in pages. Each sign-in page only signs in its own kind of account.
 */
function withTwoFactor(User $user): string
{
    $secret = (new Google2FA)->generateSecretKey();
    $user->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => encrypt(json_encode([]))])->save();

    return $secret;
}

function restaurantAccount(): User
{
    [$owner] = member(Role::Owner);
    $owner->forceFill(['email' => 'owner@example.com', 'password' => 'correct-horse-battery', 'email_verified_at' => now()])->save();

    return $owner;
}

function superAccount(): User
{
    $admin = superAdmin();
    $admin->forceFill(['email' => 'boss@example.com', 'password' => 'correct-horse-battery'])->save();

    return $admin;
}

it('shows the restaurant sign-in page in the landing page style, with Google when it is on', function () {
    PlatformSettings::save(['name' => 'Buna Menus', 'signup_open' => true]);
    $this->get('/signin')->assertOk()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('Buna Menus')
        ->assertSee('Your menu, on every phone')
        ->assertSee('action="/signin"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('Create a free account')
        ->assertDontSee('Continue with Google')
        ->assertDontSee(config('menu.super_admin.path').'/sign-in');

    config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
    PlatformSettings::save(['google_login' => true]);
    $this->get('/signin')->assertSee('Continue with Google')->assertSee('href="/auth/google"', false);
});

it('signs a restaurant account in at /signin and opens the admin', function () {
    $owner = restaurantAccount();
    $this->get('/signin?redirect=%2Fadmin%2Fdishes');
    $this->post('/signin', ['email' => 'owner@example.com', 'password' => 'correct-horse-battery'])
        ->assertRedirect('/admin/dishes');
    $this->assertAuthenticatedAs($owner);
});

it('never sends anyone off the site after signing in', function (string $to) {
    restaurantAccount();
    $this->get('/signin?redirect='.rawurlencode($to));
    $this->post('/signin', ['email' => 'owner@example.com', 'password' => 'correct-horse-battery'])->assertRedirect('/admin');
})->with(['https://evil.example/admin', '//evil.example/admin', '/super', '/admin\\@evil.example', '/somewhere']);

it('treats a Super Admin on the restaurant page like a wrong password', function () {
    superAccount();
    restaurantAccount();
    $this->from('/signin')->post('/signin', ['email' => 'boss@example.com', 'password' => 'correct-horse-battery'])
        ->assertRedirect('/signin')->assertSessionHasErrors(['email' => 'Wrong email or password.']);
    $this->assertGuest();
    $this->from('/signin')->post('/signin', ['email' => 'owner@example.com', 'password' => 'wrong-password-123'])
        ->assertSessionHasErrors(['email' => 'Wrong email or password.']);
    // The JSON sign-in is a restaurant sign-in too.
    $this->postJson('/api/v1/auth/login', ['email' => 'boss@example.com', 'password' => 'correct-horse-battery'], idem())
        ->assertUnprocessable()->assertJsonValidationErrors(['email' => 'Wrong email or password.']);
    $this->assertGuest();
});

it('signs the Super Admin in only on their own plain page, after the code', function () {
    $admin = superAccount();
    $secret = withTwoFactor($admin);
    restaurantAccount();

    $this->get('/super/sign-in')->assertOk()
        ->assertSee('Staff only.')
        ->assertSee('action="/super/sign-in"', false)
        ->assertDontSee('Your menu, on every phone')
        ->assertDontSee('Create a free account')
        ->assertDontSee('Continue with Google');

    // A restaurant account here: the same answer as a wrong password.
    $this->from('/super/sign-in')->post('/super/sign-in', ['email' => 'owner@example.com', 'password' => 'correct-horse-battery'])
        ->assertRedirect('/super/sign-in')->assertSessionHasErrors(['email' => 'Wrong email or password.']);

    $this->get('/super/sign-in?redirect=%2Fsuper%2Fplans');
    $this->post('/super/sign-in', ['email' => 'boss@example.com', 'password' => 'correct-horse-battery'])->assertRedirect('/signin/two-factor');
    $this->assertGuest();
    $this->get('/signin/two-factor')->assertOk()->assertSee('Enter your code')->assertDontSee('Your menu, on every phone');
    $this->from('/signin/two-factor')->post('/signin/two-factor', ['code' => '000000'])
        ->assertRedirect('/signin/two-factor')->assertSessionHasErrors(['code' => 'This code is not right. Try again.']);
    $this->post('/signin/two-factor', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect('/super/plans');
    $this->assertAuthenticatedAs($admin);
});

it('asks restaurant accounts with two-factor for their code on the landing-style page', function () {
    $owner = restaurantAccount();
    $secret = withTwoFactor($owner);
    $this->post('/signin', ['email' => 'owner@example.com', 'password' => 'correct-horse-battery'])->assertRedirect('/signin/two-factor');
    $this->get('/signin/two-factor')->assertOk()->assertSee('Your menu, on every phone')->assertSee('Use a recovery code instead');
    $this->post('/signin/two-factor', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect('/admin');
    $this->assertAuthenticatedAs($owner);
});

it('sends people back to sign in when the code step has expired', function () {
    $this->get('/signin/two-factor')->assertRedirect('/signin')->assertSessionHasErrors('code');
});

it('says "too many tries" on the form instead of an error page', function () {
    restaurantAccount();
    for ($i = 0; $i < 5; $i++) {
        $this->from('/signin')->post('/signin', ['email' => 'owner@example.com', 'password' => 'wrong-password-123']);
    }
    $this->from('/signin')->post('/signin', ['email' => 'owner@example.com', 'password' => 'wrong-password-123'])
        ->assertRedirect('/signin')
        ->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith('Too many tries. Please wait');
});

it('shows what the e-mail links and Google sent people back for', function (string $query, string $text) {
    $this->get('/signin?'.$query)->assertOk()->assertSee($text);
})->with([
    ['verify=done', 'Your email is confirmed.'],
    ['verify=change_expired', 'This link is too old.'],
    ['google=cancelled', 'Google sign-in was stopped.'],
    ['password=changed', 'Your password is changed.'],
    ['expired=1', 'You were signed out.'],
]);

it('keeps each area to its own people', function () {
    $owner = restaurantAccount();
    $admin = superAccount();

    // Signed out: each area's own sign-in page, with the way back.
    $this->get('/admin/dishes')->assertRedirect('/signin?redirect=%2Fadmin%2Fdishes');
    $this->get('/super/plans')->assertRedirect('/super/sign-in?redirect=%2Fsuper%2Fplans');

    // A restaurant account: the Super Admin area doesn't exist.
    $this->actingAs($owner)->get('/super')->assertNotFound();
    $this->get('/super/sign-in')->assertNotFound();
    $this->get('/signin')->assertRedirect('/admin');

    // A Super Admin: sent to their own area from the restaurant one.
    $this->actingAs($admin)->get('/admin/dishes')->assertRedirect('/super');
    $this->get('/super/sign-in')->assertRedirect('/super');
});

it('hides the Super Admin pages from addresses outside the allow-list', function () {
    $admin = superAccount();
    $secret = withTwoFactor($admin);
    config(['menu.super_admin.ips' => ['203.0.113.0/24']]);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7']);
    $this->get('/super/sign-in')->assertNotFound();
    $this->post('/super/sign-in', ['email' => 'boss@example.com', 'password' => 'correct-horse-battery'])->assertNotFound();
    $this->get('/super')->assertNotFound();

    // Signed in from an allowed address, the code step from another one still doesn't exist.
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);
    $this->post('/super/sign-in', ['email' => 'boss@example.com', 'password' => 'correct-horse-battery'])->assertRedirect('/signin/two-factor');
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7']);
    $this->post('/signin/two-factor', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertNotFound();
    $this->assertGuest();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);
    $this->post('/signin/two-factor', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect('/super');
});

it('sends the Super Admin back to their area after "Log in as"', function () {
    [, $tenant] = member(Role::Owner);
    browser();
    $this->actingAs(superAdmin())->postJson(p("restaurants/{$tenant->id}/impersonate"), [], idem())->assertOk();
    // While logged in as: the restaurant's admin, not the Super Admin area.
    $this->get('/super')->assertRedirect('/admin');
    $this->postJson('/api/v1/impersonation/stop', [], idem())->assertOk()->assertJsonPath('data.redirect', '/super');
});

it('can live at another address, and the old one is gone', function () {
    putenv('SUPER_ADMIN_PATH=office-7k2q');
    try {
        $this->refreshApplication();
        $this->get('/office-7k2q/sign-in')->assertOk();
        $this->get('/super/sign-in')->assertNotFound();
        expect(route('spa.super', ['path' => 'plans']))->toEndWith('/office-7k2q/plans');
    } finally {
        putenv('SUPER_ADMIN_PATH');
        $this->refreshApplication();
    }
});

it('shows where the area is, and turns the allow-list off from the server', function () {
    $this->artisan('super:access')->expectsOutputToContain('/super/sign-in')->expectsOutputToContain('off (any address)')->assertSuccessful();

    $env = base_path('.env.super-test');
    File::put($env, "APP_NAME=Menu\nSUPER_ADMIN_IPS=203.0.113.7\n");
    app()->loadEnvironmentFrom('.env.super-test');
    config(['menu.super_admin.ips' => ['203.0.113.7']]);
    try {
        $this->artisan('super:access --clear-ips')->expectsOutputToContain('IP allow-list is off')->assertSuccessful();
        expect(File::get($env))->toContain("SUPER_ADMIN_IPS=\n")->not->toContain('203.0.113.7')
            ->and(config('menu.super_admin.ips'))->toBe([]);
    } finally {
        File::delete($env);
        app()->loadEnvironmentFrom('.env');
        Artisan::call('config:clear');
    }
});
