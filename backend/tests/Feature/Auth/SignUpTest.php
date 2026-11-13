<?php

use App\Enums\Role;
use App\Enums\SubscriptionState;
use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SignupNotification;
use App\Notifications\VerifyEmailNotification;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

/** Self sign-up (owner, 2026-11-13): register, verify, Free plan, approval, anti-spam. */
function register(array $overrides = []): TestResponse
{
    return test()->postJson('/api/v1/signup', array_merge([
        'restaurant_name' => 'Buna & Bread',
        'slug' => 'buna-and-bread',
        'name' => 'Selam Tesfaye',
        'email' => 'Selam@Example.com ',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'currency' => 'ETB',
        'terms' => true,
    ], $overrides), idem());
}

/** The link from the "Confirm your e-mail" message. */
function verifyLink(User $user): string
{
    $url = null;
    Notification::assertSentTo($user, VerifyEmailNotification::class, function ($n) use ($user, &$url) {
        $url = VerifyEmailNotification::url($user);

        return true;
    });

    return (string) $url;
}

/** Clicks the link, as the signed-in owner (a fresh request reloads the user). */
function confirmEmail(User $user): void
{
    test()->get(verifyLink($user));
    app('auth')->forgetGuards();
    test()->actingAs($user->fresh());
}

/** Switches the test browser to the Super Admin (the owner's session cookie would sign them out). */
function asAdmin(User $admin): void
{
    test()->flushSession();
    app('auth')->forgetGuards();
    test()->actingAs($admin);
}

beforeEach(function () {
    Notification::fake();
    browser();
});

it('creates the owner and the restaurant on the Free plan, not live, and asks to confirm the e-mail', function () {
    register()->assertCreated()->assertJsonPath('data.status', 'check_inbox');

    $user = User::where('email', 'selam@example.com')->sole(); // e-mail normalised
    $tenant = Tenant::where('slug', 'buna-and-bread')->sole();
    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and(Membership::where('tenant_id', $tenant->id)->where('user_id', $user->id)->sole()->role)->toBe(Role::Owner)
        ->and($tenant->plan->slug)->toBe('free')
        ->and($tenant->trial_ends_at)->toBeNull()->and($tenant->paid_until)->toBeNull()
        ->and($tenant->currency_code)->toBe('ETB')
        ->and($tenant->signup_state)->toBe(Tenant::SIGNUP_UNVERIFIED)
        ->and($tenant->subscriptionState())->toBe(SubscriptionState::Pending)
        ->and(ActivityLog::where('action', 'signup.created')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
    Notification::assertSentTo($user, VerifyEmailNotification::class);
    $this->assertAuthenticatedAs($user);

    // Not live yet.
    $this->get('/buna-and-bread')->assertStatus(503);
});

it('has a Free plan on every install, with the proposed limits and features', function () {
    $free = Plan::where('slug', 'free')->sole();
    expect($free->price_minor)->toBe(0)
        ->and($free->limits)->toEqual(['branches' => 1, 'menus' => 2, 'items' => 30, 'photos' => 30, 'tables' => 10]);
    $features = DB::table('plan_features')->where('plan_id', $free->id)->pluck('enabled', 'feature_key')->map(fn ($v) => (bool) $v)->all();
    expect($features)->toMatchArray([
        'insights' => false, 'promotions' => false, 'import_export' => false, 'branches' => false, 'remove_branding' => false,
        'pdf_menu' => true, 'order_list' => true, 'style_grid' => true, 'style_minimal' => true, 'style_editorial' => false, 'style_bistro' => false,
    ]);
});

it('blocks the admin until the e-mail is confirmed, then goes live with a welcome e-mail', function () {
    register()->assertCreated();
    $user = User::where('email', 'selam@example.com')->sole();
    $tenant = Tenant::where('slug', 'buna-and-bread')->sole();

    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email_verified', false);
    $this->getJson(t($tenant, 'menus'))->assertForbidden()->assertJsonPath('code', 'email.unverified');

    $this->get(verifyLink($user))->assertRedirect('/admin?welcome=1');
    app('auth')->forgetGuards();
    $this->actingAs($user->fresh());

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($tenant->fresh()->signup_state)->toBeNull()
        ->and($tenant->fresh()->subscriptionState())->toBe(SubscriptionState::Active);
    $this->getJson(t($tenant, 'menus'))->assertOk();
    $this->get('/buna-and-bread')->assertOk();
    Notification::assertSentTo($user, SignupNotification::class, fn ($n) => $n->kind === 'welcome');
    expect(ActivityLog::where('action', 'signup.verified')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('handles the verification link: signed out, reused, expired, tampered', function () {
    register()->assertCreated();
    $user = User::where('email', 'selam@example.com')->sole();
    $link = verifyLink($user);
    app('auth')->forgetGuards();
    $this->flushSession();

    // Opened on another device: confirmed, then sign in.
    $this->get($link)->assertRedirect('/auth/sign-in?verify=done');
    // Used again: nothing changes.
    $this->get($link)->assertRedirect('/auth/sign-in?verify=already');

    $other = User::factory()->unverified()->create();
    $expired = VerifyEmailNotification::url($other);
    $this->travel(25)->hours();
    $this->get($expired)->assertRedirect('/auth/sign-in?verify=expired');
    $this->get(str_replace(sha1($other->email), sha1('x@example.com'), VerifyEmailNotification::url($other)))->assertRedirect('/auth/sign-in?verify=expired');
    expect($other->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('lets an unverified owner fix the e-mail address and resend the link (rate-limited)', function () {
    register()->assertCreated();
    $user = User::where('email', 'selam@example.com')->sole();

    $this->postJson('/api/v1/signup/email', ['email' => 'Selam.T@Example.com'], idem())->assertOk()->assertJsonPath('data.email', 'selam.t@example.com');
    Notification::assertSentToTimes($user->fresh(), VerifyEmailNotification::class, 2);

    // 6 a minute per person, the address change included.
    $resend = fn () => $this->postJson('/api/v1/auth/email/verification-notification', [], idem());
    foreach (range(1, 5) as $i) {
        $resend()->assertSuccessful();
    }
    $resend()->assertStatus(429);
});

it('waits for approval when the Super Admin wants to approve sign-ups, then approves or rejects', function () {
    PlatformSettings::save(['signup_approval' => true]);
    $admin = superAdmin();
    register()->assertCreated();
    $user = User::where('email', 'selam@example.com')->sole();
    $tenant = Tenant::where('slug', 'buna-and-bread')->sole();
    confirmEmail($user);

    expect($tenant->fresh()->signup_state)->toBe(Tenant::SIGNUP_PENDING);
    Notification::assertSentTo($user, SignupNotification::class, fn ($n) => $n->kind === 'pending');
    Notification::assertSentTo($admin, SignupNotification::class, fn ($n) => $n->kind === 'admin' && $n->extra['approval'] === true);
    // The owner can set up the menu meanwhile, but it isn't live.
    $this->getJson(t($tenant, 'menus'))->assertOk();
    $this->getJson('/api/v1/me')->assertJsonPath('data.tenants.0.subscription.state', 'pending')
        ->assertJsonPath('data.tenants.0.subscription.shows_banner', true);
    $this->get('/buna-and-bread')->assertStatus(503);
    asAdmin($admin);
    $this->getJson(p('restaurants?state=pending'))->assertJsonPath('data.0.id', $tenant->id)->assertJsonPath('data.0.signup_state', 'pending_approval');

    $this->postJson(p("restaurants/{$tenant->id}/approve"), [], idem())->assertOk();
    expect($tenant->fresh()->signup_state)->toBeNull();
    Notification::assertSentTo($user, SignupNotification::class, fn ($n) => $n->kind === 'approved');
    $this->get('/buna-and-bread')->assertOk();
    $this->postJson(p("restaurants/{$tenant->id}/approve"), [], idem())->assertStatus(422);

    // A second one is rejected: e-mailed with the reason, removed, slug free again.
    $this->flushSession();
    app('auth')->forgetGuards();
    register(['slug' => 'spam-cafe', 'email' => 'spam@example.com', 'restaurant_name' => 'Spam'])->assertCreated();
    $spam = User::where('email', 'spam@example.com')->sole();
    $this->get(verifyLink($spam));
    $spamTenant = Tenant::where('slug', 'spam-cafe')->sole();
    asAdmin($admin);
    $this->postJson(p("restaurants/{$spamTenant->id}/reject"), ['reason' => 'Not a restaurant'], idem())->assertOk();

    expect(Tenant::withTrashed()->where('slug', 'spam-cafe')->exists())->toBeFalse()
        ->and(User::where('email', 'spam@example.com')->exists())->toBeFalse()
        ->and(ActivityLog::where('action', 'signup.rejected')->exists())->toBeTrue()
        ->and(ActivityLog::where('action', 'signup.approved')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
    Notification::assertSentOnDemand(SignupNotification::class, fn ($n, $channels, $notifiable) => $n->kind === 'rejected' && $notifiable->routes['mail'] === 'spam@example.com' && $n->extra['reason'] === 'Not a restaurant');
});

it('tells Super Admins about new sign-ups unless switched off', function () {
    $admin = superAdmin();
    register()->assertCreated();
    $this->get(verifyLink(User::where('email', 'selam@example.com')->sole()));
    Notification::assertSentTo($admin, SignupNotification::class, fn ($n) => $n->kind === 'admin' && $n->extra['approval'] === false);

    PlatformSettings::save(['signup_notify' => false]);
    $this->flushSession();
    app('auth')->forgetGuards();
    register(['slug' => 'second-cafe', 'email' => 'two@example.com'])->assertCreated();
    $this->get(verifyLink(User::where('email', 'two@example.com')->sole()));
    Notification::assertSentToTimes($admin, SignupNotification::class, 1);
});

it('deletes sign-ups never confirmed within 7 days, freeing the slug, and logs it', function () {
    register()->assertCreated();
    $this->flushSession();
    app('auth')->forgetGuards();
    register(['slug' => 'confirmed-cafe', 'email' => 'ok@example.com'])->assertCreated();
    $this->get(verifyLink(User::where('email', 'ok@example.com')->sole()));

    $this->travel(6)->days();
    $this->artisan('signup:prune')->assertSuccessful();
    expect(Tenant::where('slug', 'buna-and-bread')->exists())->toBeTrue();

    $this->travel(2)->days();
    $this->artisan('signup:prune')->expectsOutputToContain('Deleted 1')->assertSuccessful();
    expect(Tenant::withTrashed()->where('slug', 'buna-and-bread')->exists())->toBeFalse()
        ->and(User::where('email', 'selam@example.com')->exists())->toBeFalse()
        ->and(Tenant::where('slug', 'confirmed-cafe')->exists())->toBeTrue()
        ->and(ActivityLog::where('action', 'signup.pruned')->exists())->toBeTrue();
    $this->get('/api/v1/signup/slug?slug=buna-and-bread')->assertJsonPath('data.available', true);
});

it('checks the menu address live: available, taken, reserved, invalid', function () {
    Tenant::factory()->create(['slug' => 'taken-slug']);
    $check = fn (string $s) => $this->getJson('/api/v1/signup/slug?slug='.$s)->assertOk();

    $check('my-cafe')->assertJsonPath('data.available', true)->assertJsonPath('data.reason', null);
    $check('taken-slug')->assertJsonPath('data.available', false)->assertJsonPath('data.reason', 'taken');
    $check('admin')->assertJsonPath('data.reason', 'reserved');
    $check('email')->assertJsonPath('data.reason', 'reserved');
    $check('A--b')->assertJsonPath('data.reason', 'invalid');
});

it('validates the form and creates nothing when it fails', function () {
    register(['terms' => false])->assertJsonValidationErrors('terms');
    register(['currency' => 'XXX', 'email' => 'b@example.com'])->assertJsonValidationErrors('currency');
    register(['slug' => 'admin', 'email' => 'c@example.com'])->assertJsonValidationErrors('slug');
    register(['password' => 'short', 'password_confirmation' => 'short', 'email' => 'd@example.com'])->assertJsonValidationErrors('password');
    expect(User::count())->toBe(0)->and(Tenant::where('slug', 'buna-and-bread')->exists())->toBeFalse();
});

it('never says that an e-mail already has an account', function () {
    $existing = User::factory()->create(['email' => 'selam@example.com']);

    register()->assertCreated()->assertJsonPath('data.status', 'check_inbox');

    expect(Tenant::where('slug', 'buna-and-bread')->exists())->toBeFalse();
    $this->assertGuest();
    Notification::assertSentTo($existing, SignupNotification::class, fn ($n) => $n->kind === 'existing');
});

it('blocks disposable e-mail domains from the editable list', function () {
    register(['email' => 'x@mailinator.com'])->assertJsonValidationErrors('email');
    PlatformSettings::save(['disposable_domains' => ['spam.example']]);
    register(['email' => 'x@spam.example', 'slug' => 'other-cafe'])->assertJsonValidationErrors('email');
    expect(User::count())->toBe(0);
});

it('quietly ignores bots that fill the hidden field', function () {
    register(['website' => 'http://spam.example'])->assertCreated()->assertJsonPath('data.status', 'check_inbox');
    expect(User::count())->toBe(0)->and(Tenant::where('slug', 'buna-and-bread')->exists())->toBeFalse();
});

it('checks Cloudflare Turnstile when switched on', function () {
    PlatformSettings::save(['turnstile_enabled' => true, 'turnstile_site_key' => 'site', 'turnstile_secret' => Crypt::encryptString('secret')]);
    Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);

    $this->getJson('/api/v1/signup/config')->assertJsonPath('data.turnstile_site_key', 'site');
    register(['turnstile' => 'bad'])->assertJsonValidationErrors('turnstile');
    register(['turnstile' => 'good'])->assertCreated();
    Http::assertSent(fn ($r) => $r['secret'] === 'secret' && $r['response'] === 'good');
    expect(Tenant::where('slug', 'buna-and-bread')->exists())->toBeTrue();
});

it('shows "registration is closed" when sign-up is off', function () {
    PlatformSettings::save(['signup_open' => false]);
    $this->getJson('/api/v1/signup/config')->assertJsonPath('data.open', false);
    register()->assertForbidden()->assertJsonPath('message', 'Registration is closed at the moment.');
    expect(User::count())->toBe(0);
});

it('gives new sign-ups the plan chosen in the settings', function () {
    $pro = Plan::create(['name' => ['en' => 'Pro'], 'slug' => 'pro', 'limits' => []]);
    PlatformSettings::save(['signup_plan_id' => $pro->id]);
    register()->assertCreated();
    expect(Tenant::where('slug', 'buna-and-bread')->sole()->plan_id)->toBe($pro->id);
});

it('limits sign-ups per e-mail address and per network', function () {
    RateLimiter::clear('signup:email:'.sha1('selam@example.com'));
    foreach (range(1, 3) as $i) {
        register(['slug' => 'cafe-'.$i])->assertCreated(); // later ones: "already has an account" path, same answer
        $this->flushSession();
        app('auth')->forgetGuards();
    }
    register(['slug' => 'cafe-4'])->assertStatus(429);
});

it('enforces the Free plan limits with an upgrade contact', function () {
    PlatformSettings::save(['upgrade_whatsapp' => '+251 91 555 0100', 'support_email' => 'sales@menu.example']);
    register()->assertCreated();
    $user = User::where('email', 'selam@example.com')->sole();
    confirmEmail($user);
    $tenant = Tenant::where('slug', 'buna-and-bread')->sole();
    $menu = $this->postJson(t($tenant, 'menus'), ['name' => ['en' => 'Food']], idem())->assertCreated()->json('data.id');
    $category = $this->postJson(t($tenant, 'categories'), ['menu_id' => $menu, 'name' => ['en' => 'Mains']], idem())->assertCreated()->json('data.id');
    asTenant($tenant, function () use ($category) {
        foreach (range(1, 30) as $i) {
            Item::create(['category_id' => $category, 'name' => ['en' => 'Dish '.$i], 'price_minor' => 100]);
        }
    });

    $this->postJson(t($tenant, 'items'), ['category_id' => $category, 'name' => ['en' => 'One too many'], 'price_minor' => 100], idem())
        ->assertStatus(402)->assertJsonPath('code', 'limits.items')
        ->assertJsonPath('upgrade.whatsapp', 'https://wa.me/251915550100')
        ->assertJsonPath('upgrade.email', 'sales@menu.example');
    $this->getJson(t($tenant, 'insights'))->assertStatus(402)->assertJsonPath('upgrade.whatsapp', 'https://wa.me/251915550100');
});

it('keeps self-registered restaurants apart from each other', function () {
    register()->assertCreated();
    $a = User::where('email', 'selam@example.com')->sole();
    $this->get(verifyLink($a));
    $first = Tenant::where('slug', 'buna-and-bread')->sole();

    $this->flushSession();
    app('auth')->forgetGuards();
    register(['slug' => 'other-cafe', 'email' => 'b@example.com'])->assertCreated();
    $b = User::where('email', 'b@example.com')->sole();
    $this->get(verifyLink($b));

    $this->actingAs($b)->getJson(t($first, 'menus'))->assertNotFound();
    $this->actingAs($b)->getJson(t($first))->assertNotFound();
});
