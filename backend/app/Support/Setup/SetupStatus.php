<?php

namespace App\Support\Setup;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Backups\BackupCrypto;
use App\Support\Legal\LegalText;
use App\Support\MailSettings;
use App\Support\MobileApp;
use App\Support\PlatformSettings;
use App\Support\Push\FcmClient;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a new platform still needs (setup wizard and dashboard checklist,
 * QUESTIONS #195). Each check is true when done; `optional` ones never
 * block "set up" but still show on the checklist.
 */
class SetupStatus
{
    /** @return list<array{key: string, step: int, done: bool, optional: bool}> */
    public static function checks(User $me): array
    {
        $s = PlatformSettings::all();
        $mail = MailSettings::forScreen();
        $mobile = MobileApp::settings();
        $checks = [
            ['two_factor', 1, $me->two_factor_confirmed_at !== null, false],
            ['backup_key', 1, BackupCrypto::enabled() && ! BackupCrypto::misconfigured(), false],
            ['name', 2, $s['name'] !== PlatformSettings::DEFAULTS['name'], true],
            ['logo', 2, ! empty($s['logo']), true],
            ['contact', 2, ! empty($s['support_email']) || ! empty($s['upgrade_whatsapp']), false],
            ['plans', 3, (bool) ($s['setup']['plans_checked'] ?? false), false],
            ['email', 4, $mail['source'] === 'platform' || ! in_array($mail['env_mailer'], ['log', 'array', ''], true), false],
            ['firebase', 4, app(FcmClient::class)->configured(), true],
            ['store_links', 4, ($mobile['links']['play_store'] ?? '') !== '' || ($mobile['links']['app_store'] ?? '') !== '', true],
            ['legal', 4, $s['terms'] !== LegalText::default('terms') || $s['privacy'] !== LegalText::default('privacy'), false],
            ['features', 5, (bool) ($s['setup']['features_checked'] ?? false), true],
            ['demo', 5, self::demo()->doesntExist(), false],
        ];

        return array_map(fn ($c) => ['key' => $c[0], 'step' => $c[1], 'done' => $c[2], 'optional' => $c[3]], $checks);
    }

    /**
     * Demo restaurants (DemoSeeder): every team member has an @example.com
     * address. A real restaurant never does.
     *
     * @return Builder<Tenant>
     */
    public static function demo(): Builder
    {
        return Tenant::query()
            ->whereHas('memberships')
            ->whereDoesntHave('memberships.user', fn ($q) => $q->where('email', 'not like', '%@example.com'));
    }

    public static function done(): bool
    {
        return ! empty(PlatformSettings::get('setup')['done_at'] ?? null);
    }
}
