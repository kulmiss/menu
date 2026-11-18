<?php

use App\Enums\Role;
use App\Models\User;
use App\Notifications\ServerAlertNotification;
use App\Support\Backups\Backups;
use App\Support\Ops\Alerts;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/*
 * Alerts (owner, 2026-11-19, QUESTIONS #233): server errors and failed checks
 * reach the Super Admins by e-mail and, when set up, a Telegram chat; grouped,
 * secrets never sent, never a loop when sending fails.
 */
const BOT = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsawQ';

function telegramOn(): void
{
    PlatformSettings::save(['alerts_telegram_enabled' => true, 'alerts_telegram_chat' => '-1001234567890', 'alerts_telegram_token' => Crypt::encryptString(BOT)]);
}

it('e-mails every Super Admin about a server error, then groups the next ones for 30 minutes', function () {
    Notification::fake();
    $a = superAdmin();
    $b = superAdmin();
    $owner = User::factory()->create();

    Log::error('SQLSTATE[HY000] Connection refused (password=hunter2)');
    Notification::assertSentToTimes($a, ServerAlertNotification::class, 1);
    Notification::assertSentTo($b, ServerAlertNotification::class, fn ($n) => str_contains($n->text, 'Connection refused') && ! str_contains($n->text, 'hunter2'));
    Notification::assertNotSentTo($owner, ServerAlertNotification::class);

    Log::error('second');
    Log::critical('third');
    Log::warning('only a warning');
    Notification::assertSentToTimes($a, ServerAlertNotification::class, 1);

    $this->travel(31)->minutes();
    Log::error('fourth');
    Notification::assertSentToTimes($a, ServerAlertNotification::class, 2);
    Notification::assertSentTo($a, ServerAlertNotification::class, fn ($n) => str_contains($n->text, 'fourth') && str_contains($n->text, '2 more errors happened since.'));
});

it('sends to Telegram only when switched on and complete, and never leaks the bot token', function () {
    Notification::fake();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    superAdmin();

    Log::error('first');
    Http::assertNothingSent();

    telegramOn();
    $this->travel(31)->minutes();
    Log::error('Telegram token bot'.BOT.' in a message');
    Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://api.telegram.org/bot'.BOT.'/sendMessage')
        && $r['chat_id'] === '-1001234567890' && ! str_contains($r['text'], BOT) && str_contains($r['text'], 'bot***'));
});

it('does not loop or fail the request when the alert itself cannot be sent', function () {
    superAdmin();
    telegramOn();
    Http::fake(fn () => throw new RuntimeException('network down'));
    config(['mail.default' => 'failing']);
    config(['mail.mailers.failing' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]]);

    Log::error('boom');
    expect(Alerts::send('s', 't'))->toBe(['email' => 0, 'telegram' => false]);
});

it('saves the alert settings: the token encrypted, never sent back; Telegram needs token and chat', function () {
    $admin = superAdmin();
    $this->actingAs($admin);

    $this->putJson(p('alerts'), ['alerts_telegram_enabled' => true], idem())->assertInvalid('alerts_telegram_enabled');
    $this->putJson(p('alerts'), ['alerts_telegram_token' => 'not-a-token'], idem())->assertInvalid('alerts_telegram_token');
    $this->putJson(p('alerts'), ['alerts_telegram_chat' => 'https://evil'], idem())->assertInvalid('alerts_telegram_chat');

    $res = $this->putJson(p('alerts'), ['alerts_email' => false, 'alerts_telegram_enabled' => true, 'alerts_telegram_chat' => '@menu_alerts', 'alerts_telegram_token' => BOT], idem())
        ->assertOk()->assertJsonPath('data.settings.alerts_telegram_token_set', true)->assertJsonPath('data.settings.alerts_email', false);
    expect($res->getContent())->not->toContain(BOT)->not->toContain('"alerts_telegram_token"')
        ->and(Crypt::decryptString(PlatformSettings::get('alerts_telegram_token')))->toBe(BOT)
        ->and($this->getJson(p('settings'))->getContent())->not->toContain(BOT);

    // Saving without a token keeps it.
    $this->putJson(p('alerts'), ['alerts_telegram_chat' => '-100200'], idem())->assertOk();
    expect(Alerts::token())->toBe(BOT);

    // Only Super Admins.
    [$owner] = member(Role::Owner);
    $this->actingAs($owner)->putJson(p('alerts'), ['alerts_email' => true], idem())->assertNotFound();
});

it('sends a test alert and says where it went', function () {
    Notification::fake();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $admin = superAdmin();
    telegramOn();
    $this->actingAs($admin)->postJson(p('alerts/test'), [], idem())->assertOk()->assertJsonPath('data', ['email' => 1, 'telegram' => true]);
    Notification::assertSentTo($admin, ServerAlertNotification::class, fn ($n) => str_contains($n->subject, 'test alert'));
});

it('sends a backup problem to Telegram too', function () {
    Notification::fake();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    superAdmin();
    telegramOn();
    app(Backups::class)->check();   // no backup in 26 hours
    Http::assertSent(fn ($r) => str_contains($r['text'], 'backup'));
});
