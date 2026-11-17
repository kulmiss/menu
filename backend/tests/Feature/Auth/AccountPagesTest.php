<?php

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Notifications\SignupNotification;
use App\Notifications\VerifyEmailNotification as VerifyEmail;
use App\Support\PlatformSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

/**
 * The account pages in the landing page's style (QUESTIONS #222): server
 * pages with plain forms, so they work without JavaScript.
 */
function openSignUp(array $settings = []): void
{
    PlatformSettings::save(['signup_open' => true, 'signup_approval' => false, 'name' => 'Buna Menus', ...$settings]);
}

function signUpFields(array $extra = []): array
{
    return ['name' => 'Selam Bekele', 'restaurant_name' => 'Buna & Bread', 'email' => 'selam@example.com', 'password' => 'injera-coffee-sunrise', 'terms' => '1', ...$extra];
}

it('shows the short sign-up form in the landing style, with no script files', function () {
    openSignUp(['terms' => 'Our terms', 'privacy' => 'Our privacy']);
    $html = $this->get('/signup')->assertOk()
        ->assertSee('action="/signup"', false)
        ->assertSee('Your name')->assertSee('Restaurant name')->assertSee('Email')->assertSee('Password')
        ->assertSee('href="/terms"', false)->assertSee('href="/privacy"', false)
        ->assertSee('data-strength="password-strength"', false)
        ->assertSee('Your menu, on every phone')
        ->assertDontSee('noindex')
        ->assertDontSee('Continue with Google')
        ->getContent();
    // Only inline scripts with the page's nonce: nothing to download (QUESTIONS #222 speed).
    expect(preg_match_all('/<script[^>]*\bsrc=/', $html))->toBe(0)
        ->and(preg_match_all('/<script nonce="[^"]+">/', $html))->toBe(2);

    config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
    PlatformSettings::save(['google_login' => true]);
    $this->get('/signup')->assertSee('Continue with Google');
});

it('turns the sign-up page into "Contact us on WhatsApp" while sign-up is off', function () {
    PlatformSettings::save(['signup_open' => false, 'name' => 'Buna Menus', 'upgrade_whatsapp' => '+251 911 222 333', 'support_email' => 'hello@buna.example']);
    $this->get('/signup')->assertOk()
        ->assertSee('Sign-up is closed for now')
        ->assertSee('Contact us on WhatsApp')
        ->assertSee('https://wa.me/251911222333?text=', false)
        ->assertSee('Contact us by email')
        ->assertDontSee('action="/signup"', false);
    $this->post('/signup', signUpFields())->assertRedirect('/signup');
    expect(User::where('email', 'selam@example.com')->exists())->toBeFalse();
});

it('creates the owner and restaurant, signs them in and asks them to check their inbox', function () {
    Notification::fake();
    openSignUp();
    $this->post('/signup', signUpFields())->assertRedirect('/verify-email');

    $user = User::where('email', 'selam@example.com')->sole();
    $this->assertAuthenticatedAs($user);
    expect($user->memberships()->sole()->tenant->name)->toBe('Buna & Bread');
    Notification::assertSentTo($user, VerifyEmail::class);

    $this->get('/verify-email')->assertOk()->assertSee('We sent a link to selam@example.com');
    // Until the address is confirmed the admin is only this page.
    $this->get('/admin')->assertRedirect('/verify-email');
});

it('gives the same answer for an address that already has an account', function () {
    Notification::fake();
    openSignUp();
    $known = User::factory()->create(['email' => 'selam@example.com']);
    $this->post('/signup', signUpFields())->assertRedirect('/verify-email');
    $this->assertGuest();
    Notification::assertSentTo($known, SignupNotification::class);
    $this->get('/verify-email')->assertOk()->assertSee('If this email is new to us');
});

it('shows mistakes under the fields and keeps what was typed (never the password)', function () {
    openSignUp();
    $this->from('/signup')->post('/signup', signUpFields(['email' => 'not-an-email', 'terms' => null, 'password' => 'short']))
        ->assertRedirect('/signup')->assertSessionHasErrors(['email', 'terms', 'password']);
    expect(session()->getOldInput())->toHaveKey('restaurant_name')->not->toHaveKey('password');

    // The page shows them. Set as the JSON session stores them (config/session.php "serialization").
    $this->withSession(['errors' => ['default' => ['format' => ':message', 'messages' => ['email' => ['Use a real email.']]]], '_old_input' => ['restaurant_name' => 'Buna & Bread']])
        ->get('/signup')
        ->assertSee('value="Buna &amp; Bread"', false)
        ->assertSee('aria-invalid="true" aria-describedby="email-error"', false)
        ->assertSee('Use a real email.');
});

it('ignores bots that fill the hidden field', function () {
    openSignUp();
    $this->post('/signup', signUpFields(['website' => 'http://spam.example']))->assertRedirect('/verify-email');
    expect(User::count())->toBe(0);
});

it('says "too many tries" on the form, not on an error page', function () {
    Notification::fake();
    openSignUp();
    foreach (range(1, 3) as $i) {
        $this->post('/signup', signUpFields(['website' => 'x']));
    }
    $this->from('/signup')->post('/signup', signUpFields())->assertRedirect('/signup')->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toBe('Too many tries. Please try again in an hour.');
});

it('resends the link, changes a wrong address and checks it was opened', function () {
    Notification::fake();
    openSignUp();
    $this->post('/signup', signUpFields());
    $user = User::where('email', 'selam@example.com')->sole();

    $this->post('/verify-email/resend')->assertRedirect()->assertSessionHas('status', 'We sent a new link to selam@example.com.');
    $this->post('/verify-email/change', ['email' => 'selam.b@example.com'])->assertRedirect('/verify-email');
    expect($user->fresh()->email)->toBe('selam.b@example.com');
    Notification::assertSentToTimes($user, VerifyEmail::class, 3);

    $this->post('/verify-email/check')->assertRedirect('/verify-email')->assertSessionHas('warning');
    $user->fresh()->markEmailAsVerified();
    $this->actingAs($user->fresh()); // a new request reads the account again
    $this->post('/verify-email/check')->assertRedirect('/admin');
    $this->get('/verify-email')->assertRedirect('/admin');
});

it('says "we are checking your restaurant" after the link when sign-up needs approval', function () {
    Notification::fake();
    openSignUp(['signup_approval' => true]);
    $this->post('/signup', signUpFields());
    $user = User::where('email', 'selam@example.com')->sole();
    $link = URL::temporarySignedRoute('signup.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->get($link)->assertRedirect('/waiting');
    $this->get('/waiting')->assertOk()->assertSee('Thanks! We are checking your restaurant')->assertSee('Buna &amp; Bread', false)->assertSee('href="/admin?welcome=1"', false);

    // Approved (or no approval needed): nothing to wait for.
    $user->memberships()->sole()->tenant->forceFill(['signup_state' => null])->save();
    $this->get('/waiting')->assertRedirect('/admin');
});

it('sends the same "check your inbox" for any address on "Forgot password"', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'known@example.com']);
    $this->get('/forgot-password')->assertOk()->assertSee('action="/forgot-password"', false)->assertSee('<meta name="robots" content="noindex">', false);

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'known@example.com'])
        ->assertRedirect('/forgot-password')->assertSessionHas('status', 'If known@example.com has an account, the link is on its way. Look in your inbox and spam folder.');
    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com'])
        ->assertRedirect('/forgot-password')->assertSessionHas('status', 'If nobody@example.com has an account, the link is on its way. Look in your inbox and spam folder.');
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
        return str_starts_with($n->toMail($user)->actionUrl, url('/reset-password?token='));
    });

    // Limits: 5 a minute from one address.
    foreach (range(1, 3) as $i) {
        $this->post('/forgot-password', ['email' => "a{$i}@example.com"]);
    }
    // Too many: said on the form, not an error page.
    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'a9@example.com'])
        ->assertRedirect('/forgot-password')->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith('Too many tries. Please wait');
});

it('saves a new password from the e-mail link and says so on the sign-in page', function () {
    $user = User::factory()->create(['email' => 'known@example.com']);
    $token = Password::broker()->createToken($user);

    $this->get('/reset-password?token='.$token.'&email=known%40example.com')->assertOk()
        ->assertSee('Choose a new password')->assertSee('name="token" value="'.$token.'"', false);
    $this->post('/reset-password', ['token' => $token, 'email' => 'known@example.com', 'password' => 'another-long-secret'])
        ->assertRedirect('/signin?password=changed');
    $this->get('/signin?password=changed')->assertSee('Your password is changed.');

    $this->from('/reset-password?token=old&email=known%40example.com')
        ->post('/reset-password', ['token' => 'old', 'email' => 'known@example.com', 'password' => 'yet-another-secret'])
        ->assertSessionHasErrors(['email' => 'This link is too old or not right. Ask for a new one below.']);
    $this->get('/reset-password')->assertOk()->assertSee('This link is not complete or too old.');
});

it('opens team invitations for new and existing people', function () {
    Notification::fake();
    [$owner, $tenant] = member(Role::Owner);
    $token = 'invite-'.str_repeat('a', 30);
    asTenant($tenant, fn () => Invitation::create(['email' => 'aster@example.com', 'role' => Role::Manager, 'token_hash' => Invitation::hashToken($token), 'expires_at' => now()->addDays(3), 'invited_by' => $owner->id]));

    $this->get('/invite/nope')->assertOk()->assertSee('This invitation does not work');
    $this->get('/invite/'.$token)->assertOk()
        ->assertSee('Join '.e($tenant->name), false)->assertSee('aster@example.com')->assertSee('Manager')
        ->assertSee('action="/invite/'.$token.'/join"', false);

    // No account yet: name + password, then in the restaurant.
    $this->post('/invite/'.$token.'/join', ['name' => 'Aster', 'password' => 'aster-long-secret'])
        ->assertRedirect('/admin?restaurant='.$tenant->id);
    $aster = User::where('email', 'aster@example.com')->sole();
    $this->assertAuthenticatedAs($aster);
    expect($aster->hasVerifiedEmail())->toBeTrue()
        ->and(Membership::where('user_id', $aster->id)->sole()->role)->toBe(Role::Manager);
    $this->get('/invite/'.$token)->assertSee('This invitation does not work');
});

it('lets the invited account accept, and asks anyone else to switch', function () {
    [$owner, $tenant] = member(Role::Owner);
    $token = 'invite-'.str_repeat('b', 30);
    asTenant($tenant, fn () => Invitation::create(['email' => 'aster@example.com', 'role' => Role::Staff, 'token_hash' => Invitation::hashToken($token), 'expires_at' => now()->addDays(3), 'invited_by' => $owner->id]));
    $aster = User::factory()->create(['email' => 'aster@example.com']);

    // Signed out, with an account: sign in first, then back here.
    $this->get('/invite/'.$token)->assertSee('href="/signin?redirect=%2Finvite%2F'.$token.'"', false)->assertDontSee('/join"', false);

    $this->actingAs($owner)->get('/invite/'.$token)->assertSee('This invitation is for aster@example.com')->assertSee('Sign out and use another account');
    $this->post('/signout', ['redirect' => '/invite/'.$token])->assertRedirect('/signin?redirect=%2Finvite%2F'.$token);
    $this->assertGuest();

    $this->actingAs($aster)->get('/invite/'.$token)->assertSee('action="/invite/'.$token.'/accept"', false);
    $this->post('/invite/'.$token.'/accept')->assertRedirect('/admin?restaurant='.$tenant->id);
    expect(Membership::where('user_id', $aster->id)->sole()->tenant_id)->toBe($tenant->id);
});

it('links every e-mail and the app to the new pages', function () {
    Notification::fake();
    [$owner, $tenant] = member(Role::Owner);
    $this->actingAs($owner)->postJson(t($tenant, 'invitations'), ['email' => 'new@example.com', 'role' => 'staff'], idem())->assertCreated();
    Notification::assertSentOnDemand(InvitationNotification::class, function (InvitationNotification $n, array $channels, object $notifiable) {
        return str_starts_with($n->toMail($notifiable)->actionUrl, url('/invite/'));
    });
    $this->getJson('/api/v1/mobile/config')->assertJsonPath('data.links.forgot_password', url('/forgot-password'))->assertJsonPath('data.links.signup', url('/signup'));
});

it('finishes "Continue with Google" for a new owner on its own page', function () {
    openSignUp();
    config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
    PlatformSettings::save(['google_login' => true]);
    $this->get('/signup/google')->assertRedirect('/signin?google=expired');

    $this->withSession(['google.pending' => ['sub' => 'g-1', 'email' => 'yonas@gmail.com', 'email_verified' => true, 'name' => 'Yonas', 'at' => now()->timestamp]]);
    $this->get('/signup/google')->assertOk()->assertSee('You are signing up with Google as yonas@gmail.com');
    $this->from('/signup/google')->post('/signup/google', ['restaurant_name' => 'Yonas Café'])->assertSessionHasErrors('terms');
    $this->post('/signup/google', ['restaurant_name' => 'Yonas Café', 'terms' => '1'])->assertRedirect('/admin');
    expect(Tenant::where('name', 'Yonas Café')->exists())->toBeTrue();
    $this->assertAuthenticatedAs(User::where('email', 'yonas@gmail.com')->sole());
});

it('signs out from the account pages back to the sign-in page', function () {
    [$owner] = member(Role::Owner);
    $this->actingAs($owner)->post('/signout')->assertRedirect('/signin');
    $this->assertGuest();
    // A Super Admin goes back to their own sign-in page.
    $this->actingAs(superAdmin())->post('/signout')->assertRedirect('/super/sign-in');
});

it('says "too many tries" on every account form instead of an error page', function (string $from, string $to, array $fields, string $field, int $limit) {
    openSignUp();
    for ($i = 0; $i < $limit; $i++) {
        $this->from($from)->post($to, $fields);
    }
    $this->from($from)->post($to, $fields)->assertRedirect($from)->assertSessionHasErrors($field);
})->with([
    'sign-up' => ['/signup', '/signup', ['website' => 'bot'], 'email', 10],
    'reset password' => ['/reset-password?token=x&email=a%40example.com', '/reset-password', ['token' => 'x', 'email' => 'a@example.com', 'password' => 'another-long-secret'], 'password', 5],
    'invitation' => ['/invite/nope', '/invite/nope/join', ['name' => 'X', 'password' => 'another-long-secret'], 'name', 10],
]);

it('says "too many tries" on the "check your inbox" page too', function () {
    Notification::fake();
    openSignUp();
    $this->post('/signup', signUpFields());
    for ($i = 0; $i < 6; $i++) {
        $this->from('/verify-email')->post('/verify-email/resend');
    }
    $this->from('/verify-email')->post('/verify-email/resend')->assertRedirect('/verify-email')->assertSessionHas('warning');
});
