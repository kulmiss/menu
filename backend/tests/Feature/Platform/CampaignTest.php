<?php

use App\Enums\Role;
use App\Jobs\SendCampaignBatch;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Plan;
use App\Models\PlatformTag;
use App\Models\Tenant;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/** Super Admin → Messages (owner, 2026-11-13). */
function campaign(array $filters = [], string $body = 'Hello {owner_name}, your menu {restaurant_name} on {plan}: {menu_link}'): array
{
    return ['subject' => 'News for {restaurant_name}', 'body' => $body, 'filters' => $filters];
}

beforeEach(fn () => Mail::fake());

it('counts and picks recipients by plan, status, tag, inactivity and sign-up date', function () {
    $this->actingAs(superAdmin());
    $pro = Plan::create(['name' => ['en' => 'Pro'], 'slug' => 'pro-test', 'price_minor' => 100, 'currency' => 'ETB', 'interval' => 'month', 'limits' => [], 'is_public' => true, 'is_active' => true]);
    [, $active] = member(Role::Owner);
    $active->forceFill(['plan_id' => $pro->id, 'paid_until' => now()->addMonths(2), 'trial_ends_at' => null])->save();
    [, $expiring] = member(Role::Owner);
    $expiring->forceFill(['paid_until' => now()->addDays(5), 'trial_ends_at' => null])->save();
    [, $expired] = member(Role::Owner);
    $expired->forceFill(['paid_until' => now()->subDays(2), 'trial_ends_at' => null])->save();
    [, $suspended] = member(Role::Owner);
    $suspended->forceFill(['status' => Tenant::STATUS_SUSPENDED, 'suspended_at' => now()])->save();
    [, $old] = member(Role::Owner);
    $old->forceFill(['created_at' => now()->subDays(60), 'trial_ends_at' => now()->addDays(60)])->save();
    [$unsubscribed, $quiet] = member(Role::Owner);
    $quiet->forceFill(['trial_ends_at' => now()->addDays(60)])->save();
    $suspended->forceFill(['trial_ends_at' => now()->addDays(60)])->save();
    $unsubscribed->forceFill(['news_unsubscribed_at' => now()])->save();
    $vip = PlatformTag::create(['name' => 'VIP']);
    $active->platformTags()->attach($vip);

    $count = fn (array $f) => $this->postJson(p('campaigns/count'), campaign($f), idem())->assertOk()->json('data.count');
    expect($count([]))->toBe(5) // everyone except the unsubscribed owner
        ->and($count(['plan_id' => $pro->id]))->toBe(1)
        ->and($count(['status' => 'expiring']))->toBe(1)
        ->and($count(['status' => 'expired']))->toBe(1)
        ->and($count(['status' => 'suspended']))->toBe(1)
        ->and($count(['tag_id' => $vip->id]))->toBe(1)
        ->and($count(['inactive_days' => 30]))->toBe(1) // only the 60-day-old restaurant, with no views or edits
        ->and($count(['signed_up_to' => now()->subDays(30)->toDateString()]))->toBe(1);
    $this->postJson(p('campaigns/count'), campaign(['status' => 'happy']), idem())->assertUnprocessable();
});

it('previews with real placeholders, escapes HTML and sends a test to the Super Admin', function () {
    $admin = superAdmin();
    $this->actingAs($admin);
    [$owner, $tenant] = member(Role::Owner);
    // A fixed name with an apostrophe: random names sometimes had one, and the test compared it with e(), which escapes it as &#039; while the message text keeps it as is (safe outside attributes).
    $owner->update(['name' => "Abebe O'Neil"]);
    $tenant->update(['name' => 'Café <b>Bold</b>']);

    $preview = $this->postJson(p('campaigns/preview'), campaign([], "**Hi** {owner_name}\n\n<script>x</script>\n\n[Open]({menu_link})"), idem())->assertOk()->json('data');
    expect($preview['subject'])->toBe('News for Café <b>Bold</b>')
        ->and($preview['html'])->toContain("<strong>Hi</strong> Abebe O'Neil</p>")->toContain('&lt;script&gt;')
        ->toContain('href="'.url('/'.$tenant->slug).'"')->not->toContain('<script>');

    $this->postJson(p('campaigns/test'), campaign(), idem())->assertOk()->assertJsonPath('data.sent_to', $admin->email);
    Mail::assertSent(CampaignMail::class, fn (CampaignMail $m) => $m->hasTo($admin->email) && str_starts_with($m->envelope()->subject, '[Test] News for'));
});

it('sends through the queue at the configured rate, retries failures and records each recipient', function () {
    $this->actingAs(superAdmin());
    foreach (range(1, 5) as $i) {
        member(Role::Owner);
    }
    PlatformSettings::save(['campaign_rate' => 2]);
    $id = $this->postJson(p('campaigns'), campaign(), idem())->assertCreated()->json('data.id');
    $this->postJson(p('campaigns'), campaign(['tag_id' => null, 'plan_id' => 999999]), idem())->assertUnprocessable();

    (new SendCampaignBatch)->handle();
    Mail::assertSentCount(2);
    (new SendCampaignBatch)->handle();
    (new SendCampaignBatch)->handle();
    Mail::assertSentCount(5);
    expect(Campaign::find($id)->status)->toBe('done')
        ->and(CampaignRecipient::where('status', 'sent')->count())->toBe(5);

    $mail = Mail::sent(CampaignMail::class)->first();
    expect($mail->unsubscribeUrl)->toContain('/news/')->and($mail->headers()->text['List-Unsubscribe'])->toContain('signature=');

    // A recipient whose mail server refuses: three tries, then "failed"; "resend to failed" queues it again.
    $bad = CampaignRecipient::first();
    $bad->update(['status' => 'pending', 'email' => 'not an address']);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('550 mailbox unavailable'));
    foreach (range(1, 3) as $_) {
        (new SendCampaignBatch)->handle();
    }
    expect($bad->fresh())->status->toBe('failed')->attempts->toBe(3)->error->toContain('550');
    $this->getJson(p("campaigns/{$id}"))->assertOk()->assertJsonPath('data.failed', 1)->assertJsonPath('data.sent', 4)
        ->assertJsonPath('data.list.0.status', 'failed');
    $this->postJson(p("campaigns/{$id}/retry"), [], idem())->assertOk()->assertJsonPath('data.requeued', 1);
    expect($bad->fresh()->status)->toBe('pending')->and(Campaign::find($id)->status)->toBe('sending');
});

it('lets owners unsubscribe from news by link or in their profile; account e-mails are separate', function () {
    [$owner, $tenant] = member(Role::Owner);
    $url = URL::signedRoute('news.unsubscribe', ['user' => $owner->id]);

    $page = $this->get($url)->assertOk()->assertSee('Unsubscribe');
    expect($page->headers->getCookies())->toBe([]); // a public page: no session or CSRF cookie (pre-launch check)
    $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
    // One-click POST from a mail client: no CSRF token, signed link.
    $done = $this->post(URL::signedRoute('news.unsubscribe.do', ['user' => $owner->id]))->assertOk()->assertSee('You are unsubscribed');
    expect($done->headers->getCookies())->toBe([]);
    expect($owner->fresh()->news_unsubscribed_at)->not->toBeNull();

    $this->actingAs(superAdmin());
    $this->postJson(p('campaigns/count'), campaign(), idem())->assertJsonPath('data.count', 0);

    app('auth')->forgetGuards();
    $this->actingAs($owner->fresh())->getJson('/api/v1/me')->assertJsonPath('data.news_subscribed', false);
    $this->putJson('/api/v1/me/news', ['subscribed' => true], idem())->assertOk();
    expect($owner->fresh()->news_unsubscribed_at)->toBeNull();
});

it('is for Super Admins only and changes the sending rate', function () {
    [$owner] = member(Role::Owner);
    $this->actingAs($owner)->postJson(p('campaigns'), campaign(), idem())->assertNotFound();
    app('auth')->forgetGuards();
    $this->actingAs(superAdmin())->putJson(p('campaigns/rate'), ['campaign_rate' => 60], idem())->assertOk();
    expect(PlatformSettings::get('campaign_rate'))->toBe(60);
    $this->putJson(p('campaigns/rate'), ['campaign_rate' => 0], idem())->assertUnprocessable();
});
