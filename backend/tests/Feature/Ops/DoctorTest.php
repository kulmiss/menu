<?php

use App\Models\Backup;
use App\Models\User;
use App\Support\Ops\Doctor;
use Illuminate\Support\Facades\Cache;

/*
 * menu:doctor and /health (owner, 2026-11-19, QUESTIONS #233): every unsafe or
 * missing thing is red with a fix, the command exits 1 so a deploy stops, and
 * /health tells uptime monitors only the overall state.
 */

function doctor(bool $production = true): array
{
    return collect(app(Doctor::class)->run($production, quick: true))->keyBy('key')->all();
}

function ready(): void
{
    config(['app.debug' => false, 'app.url' => 'https://menu.example.com', 'session.secure' => true, 'backups.key' => 'base64:'.base64_encode(random_bytes(32)),
        'logging.channels.stack.channels' => ['daily'], 'mail.default' => 'smtp', 'menu.super_admin.path' => 'office-7k2q']);
    Cache::forever('cron:last_run', now()->toIso8601String());
    DB::table('platform_settings')->updateOrInsert(['key' => 'backup_enabled'], ['value' => json_encode(true)]);
    Cache::forget('platform_settings.stored');
    Backup::create(['filename' => 'b.zip', 'kind' => 'scheduled', 'status' => 'ok', 'size' => 1, 'started_at' => now(), 'finished_at' => now()]);
    $admin = User::factory()->create(['email' => 'owner@menu-platform.test', 'is_super_admin' => true]);
    $admin->forceFill(['two_factor_confirmed_at' => now()])->save();
}

it('turns red for an unsafe live server and says how to fix each thing', function () {
    config(['app.debug' => true, 'app.url' => 'http://menu.example.com', 'session.secure' => false, 'backups.key' => '']);
    $c = doctor();

    expect($c['app_debug']['status'])->toBe('fail')->and($c['app_debug']['fix'])->toContain('APP_DEBUG=false')
        ->and($c['app_url']['status'])->toBe('fail')
        ->and($c['secure_cookie']['status'])->toBe('fail')
        ->and($c['backup_key']['status'])->toBe('fail')
        ->and($c['cron']['status'])->toBe('fail')                // never ran
        ->and($c['super_admin']['status'])->toBe('fail')         // none yet
        ->and($c['super_admin']['fix'])->toContain('super:create')
        ->and($c['backups']['status'])->toBe('fail');            // off
    expect(Doctor::overall(array_values($c)))->toBe('down');
    $this->artisan('menu:doctor --production')->assertExitCode(1);
});

it('is green, maybe with warnings, when the server is ready', function () {
    // The admin app is built on a real server; CI's backend job has no build (failed there, 2026-11-19).
    withBuiltSpa(function () {
        ready();
        $c = doctor();
        $red = array_filter($c, fn ($x) => $x['status'] === 'fail');

        expect($red)->toBe([])
            ->and($c['super_admin']['message'])->toBe('1 Super Admin(s), all with two-factor')
            ->and($c['backups']['status'])->toBe('ok')
            ->and($c['backup_offsite']['status'])->toBe('warn')     // off-site copy is optional
            ->and(collect($c)->every(fn ($x) => $x['status'] === 'ok' ? $x['fix'] === null : $x['fix'] !== null))->toBeTrue();
        $this->artisan('menu:doctor --production')->assertExitCode(0);
    });
});

it('flags a Super Admin without two-factor, the default /super address and the demo admin', function () {
    ready();
    User::factory()->create(['email' => 'admin@example.com', 'is_super_admin' => true]);
    config(['menu.super_admin.path' => 'super']);
    $c = doctor();

    expect($c['super_admin']['status'])->toBe('fail')->and($c['super_admin']['message'])->toContain('1 Super Admin(s) without two-factor')
        ->and($c['super_admin_path']['status'])->toBe('fail')
        ->and($c['default_admin']['status'])->toBe('fail');
});

it('prints JSON for scripts and never shows a password', function () {
    config(['database.connections.mysql.password' => 'Sup3r-secret!']);
    $this->artisan('menu:doctor --json')->expectsOutputToContain('"status":')->doesntExpectOutputToContain('Sup3r-secret!')->assertExitCode(1);
});

it('answers /health with the overall state only: 503 when down, 200 when ready', function () {
    // The admin app is built on a real server; CI's backend job has no build (failed there, 2026-11-19).
    withBuiltSpa(function () {
        $this->get('/health')->assertStatus(503)->assertExactJson(['status' => 'down'])->assertHeader('Cache-Control', 'no-store, private');
        Cache::forget('health:status');
        ready();
        app()->detectEnvironment(fn () => 'production');
        $res = $this->get('/health')->assertOk();
        expect($res->json('status'))->toBeIn(['ok', 'degraded'])
            ->and($res->getContent())->not->toContain('fix');
        expect(config('menu.reserved_slugs'))->toContain('health');
    });
});

it('creates a Super Admin from the command line, with a strong password typed twice', function () {
    $this->artisan('super:create', ['--name' => 'Abebe', '--email' => 'Abebe@Example.org'])
        ->expectsQuestion('Password (at least 10 characters)', 'Lion-Coffee-42!')
        ->expectsQuestion('Password again', 'Lion-Coffee-42!')
        ->assertExitCode(0);
    $u = User::where('email', 'abebe@example.org')->sole();
    expect($u->is_super_admin)->toBeTrue()->and($u->email_verified_at)->not->toBeNull()->and(Hash::check('Lion-Coffee-42!', $u->password))->toBeTrue();

    $this->artisan('super:create', ['--name' => 'X', '--email' => 'x@example.org'])
        ->expectsQuestion('Password (at least 10 characters)', 'short')
        ->expectsQuestion('Password again', 'short')
        ->assertExitCode(1);
    $this->artisan('super:create', ['--name' => 'X', '--email' => 'x@example.org'])
        ->expectsQuestion('Password (at least 10 characters)', 'Lion-Coffee-42!')
        ->expectsQuestion('Password again', 'Lion-Coffee-43!')
        ->assertExitCode(1);
    expect(User::where('email', 'x@example.org')->exists())->toBeFalse();
});
