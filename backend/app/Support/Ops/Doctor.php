<?php

namespace App\Support\Ops;

use App\Models\Backup;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Backups\Backups;
use App\Support\MailSettings;
use App\Support\PlatformSettings;
use App\Support\Security\SuperAdminAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Go-live checks (owner, 2026-11-19, QUESTIONS #233): everything that makes a
 * live server unsafe or broken, each with what to do. Used by `php artisan
 * menu:doctor` and, for the overall state only, by the public /health address.
 *
 * "fail" = unsafe or broken: fix before going live (the command exits 1).
 * "warn" = works, but should be fixed soon or is not checked here.
 */
final class Doctor
{
    public const PHP_EXTENSIONS = ['ctype', 'curl', 'exif', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'zip'];

    /** @var list<array{key: string, status: 'ok'|'warn'|'fail', message: string, fix: string|null}> */
    private array $checks = [];

    /**
     * @param  bool|null  $production  check as for a live server (default: when APP_ENV is production)
     * @param  bool  $quick  skip the slow network check (mail DNS), for /health
     * @return list<array{key: string, status: 'ok'|'warn'|'fail', message: string, fix: string|null}>
     */
    public function run(?bool $production = null, bool $quick = false): array
    {
        $this->checks = [];
        $live = $production ?? app()->environment('production');

        $this->app($live);
        $this->php();
        $database = $this->database();
        $this->folders();
        $this->build();
        if ($database) {
            $this->cron();
            $this->queue();
            $this->backups($live);
            $this->superAdmin($live);
            $this->demoData($live);
        }
        $this->mail($live, $quick);
        $this->disk();

        return $this->checks;
    }

    /**
     * ok, degraded (a warning) or down (a failure): for /health and the exit code.
     *
     * @param  list<array{key: string, status: 'ok'|'warn'|'fail', message: string, fix: string|null}>  $checks
     */
    public static function overall(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        return in_array('fail', $statuses, true) ? 'down' : (in_array('warn', $statuses, true) ? 'degraded' : 'ok');
    }

    private function add(string $key, string $status, string $message, ?string $fix = null): void
    {
        $this->checks[] = ['key' => $key, 'status' => $status, 'message' => $message, 'fix' => $status === 'ok' ? null : $fix];
    }

    private function app(bool $live): void
    {
        $key = (string) config('app.key');
        $this->add('app_key', $key !== '' ? 'ok' : 'fail', $key !== '' ? 'APP_KEY is set' : 'APP_KEY is empty', 'php artisan key:generate --force, then keep a copy of .env in your password manager');

        $debug = (bool) config('app.debug');
        $this->add('app_debug', $live && $debug ? 'fail' : 'ok', $debug ? 'APP_DEBUG is on (shows code and secrets on errors)' : 'APP_DEBUG is off', 'Set APP_DEBUG=false in .env, then php artisan config:cache');

        $this->add('app_env', $live ? 'ok' : 'warn', 'APP_ENV is '.app()->environment(), 'Set APP_ENV=production on the live server');

        $url = (string) config('app.url');
        $https = str_starts_with($url, 'https://');
        $this->add('app_url', $https || ! $live ? 'ok' : 'fail', "APP_URL is {$url}", 'Use the https:// address people type, e.g. APP_URL=https://menu.example.com');

        $secure = (bool) config('session.secure');
        $this->add('secure_cookie', ! $live || $secure ? 'ok' : 'fail', $secure ? 'Session cookie is HTTPS-only' : 'Session cookie also goes over plain HTTP', 'Set SESSION_SECURE_COOKIE=true');

        $cached = app()->configurationIsCached();
        $this->add('config_cache', ! $live || $cached ? 'ok' : 'warn', $cached ? 'Config is cached' : 'Config is not cached (slower)', 'php artisan optimize');

        $log = (string) config('logging.channels.stack.channels.0', config('logging.default'));
        $daily = in_array('daily', (array) config('logging.channels.stack.channels', []), true) || config('logging.default') === 'daily';
        $this->add('log_rotation', $daily ? 'ok' : 'warn', $daily ? 'Logs rotate daily (kept '.(int) config('logging.channels.daily.days', 14).' days)' : "Logs go to one growing file ({$log})", 'Set LOG_STACK=daily');

        $backupKey = (string) config('backups.key');
        $this->add('backup_key', $backupKey !== '' ? 'ok' : ($live ? 'fail' : 'warn'), $backupKey !== '' ? 'Backups are encrypted (BACKUP_KEY)' : 'Backups are not encrypted (no BACKUP_KEY)', 'php artisan backup:key, put the line in .env and in your password manager');
    }

    private function php(): void
    {
        $this->add('php_version', version_compare(PHP_VERSION, '8.3.0', '>=') ? 'ok' : 'fail', 'PHP '.PHP_VERSION, 'Use PHP 8.3 or newer');

        $missing = array_values(array_filter(self::PHP_EXTENSIONS, fn (string $e) => ! extension_loaded($e)));
        $this->add('php_extensions', $missing === [] ? 'ok' : 'fail', $missing === [] ? 'PHP extensions present' : 'Missing PHP extensions: '.implode(', ', $missing), 'Turn them on (cPanel: Select PHP Version > Extensions; VPS: apt install php8.3-<name>)');

        $webp = function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false);
        $this->add('gd_webp', $webp ? 'ok' : 'fail', $webp ? 'GD can write WebP photos' : 'GD cannot write WebP (photo uploads fail)', 'Install GD with WebP support');

        $upload = self::bytes((string) ini_get('upload_max_filesize'));
        $post = self::bytes((string) ini_get('post_max_size'));
        $ok = $upload >= 10 * 1048576 && $post >= 12 * 1048576;
        $this->add('php_upload', $ok ? 'ok' : 'warn', 'upload_max_filesize '.ini_get('upload_max_filesize').', post_max_size '.ini_get('post_max_size'), 'upload_max_filesize = 10M, post_max_size = 12M (big phone photos)');
    }

    private function database(): bool
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->add('database', 'fail', 'No database connection: '.self::short($e->getMessage()), 'Check DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env');

            return false;
        }
        $this->add('database', 'ok', 'Database connection works');

        try {
            $ran = DB::table('migrations')->pluck('migration')->all();
            $files = array_map(fn (string $f) => basename($f, '.php'), glob(database_path('migrations/*.php')) ?: []);
            $pending = array_values(array_diff($files, $ran));
            $this->add('migrations', $pending === [] ? 'ok' : 'fail', $pending === [] ? 'All migrations ran' : count($pending).' migration(s) not run yet', 'php artisan migrate --force');
        } catch (Throwable) {
            $this->add('migrations', 'fail', 'The database is empty', 'php artisan migrate --force');

            return false;
        }

        return Schema::hasTable('platform_settings');
    }

    private function folders(): void
    {
        $paths = ['storage/app', 'storage/framework/cache', 'storage/framework/views', 'storage/logs', 'bootstrap/cache', 'public/media', 'public/menu-cache'];
        $bad = [];
        foreach ($paths as $p) {
            $full = base_path($p);
            if (! is_dir($full)) {
                @mkdir($full, 0755, true);
            }
            if (! is_dir($full) || ! is_writable($full)) {
                $bad[] = $p;
            }
        }
        $this->add('folders', $bad === [] ? 'ok' : 'fail', $bad === [] ? 'Upload, cache and log folders are writable' : 'Not writable: '.implode(', ', $bad), 'chmod -R 775 storage bootstrap/cache public/media public/menu-cache (owner = the web server user)');

        $exposed = is_file(public_path('.env'));
        $this->add('env_private', $exposed ? 'fail' : 'ok', $exposed ? 'A .env file is inside the web folder' : '.env is outside the web folder', 'Point the document root to backend/public and remove any .env from it');
    }

    private function build(): void
    {
        $built = is_file(public_path('app/index.html')) || glob(public_path('app/assets/*.js')) !== [];
        $this->add('frontend_build', $built ? 'ok' : 'fail', $built ? 'Admin app is built' : 'Admin app is not built (public/app is empty)', 'cd frontend && pnpm install && pnpm build');
    }

    private function cron(): void
    {
        $last = Cache::get('cron:last_run');
        $age = $last ? (int) Carbon::parse($last)->diffInMinutes(now(), true) : null;
        $this->add('cron', $age !== null && $age <= 10 ? 'ok' : 'fail', $age === null ? 'The scheduler has never run' : "The scheduler last ran {$age} min ago", 'Add the cron line: * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1');
    }

    private function queue(): void
    {
        $failed = (int) DB::table('failed_jobs')->count();
        $old = (int) DB::table('jobs')->where('created_at', '<', now()->subMinutes(15)->getTimestamp())->count();
        $status = $failed > 0 || $old > 0 ? 'warn' : 'ok';
        $this->add('queue', $status, "Queue: {$old} job(s) waiting over 15 min, {$failed} failed", 'Check the cron line; php artisan queue:failed shows why jobs failed');
    }

    private function backups(bool $live): void
    {
        $enabled = (bool) PlatformSettings::get('backup_enabled');
        if (! $enabled) {
            $this->add('backups', $live ? 'fail' : 'warn', 'Nightly backups are off', 'Super Admin > Backups > switch on, then Back up now');

            return;
        }
        $problem = Backups::problem();
        $this->add('backups', $problem === null ? 'ok' : ($live ? 'fail' : 'warn'), $problem ?? 'Last backup '.Carbon::parse((string) Backup::where('status', 'ok')->latest('finished_at')->value('finished_at'))->diffForHumans(), 'Super Admin > Backups > Back up now, and check the cron line');
        $this->add('backup_offsite', PlatformSettings::get('backup_s3_enabled') ? 'ok' : 'warn', PlatformSettings::get('backup_s3_enabled') ? 'Backups are also copied off-site' : 'Backups stay on this server only', 'Super Admin > Backups > Copy to S3-compatible storage (Backblaze B2, Cloudflare R2…)');
    }

    private function superAdmin(bool $live): void
    {
        $admins = User::where('is_super_admin', true)->get();
        if ($admins->isEmpty()) {
            $this->add('super_admin', 'fail', 'There is no Super Admin', 'php artisan super:create (docs/deploy.md section 8)');

            return;
        }
        $without = $admins->filter(fn (User $u) => $u->two_factor_confirmed_at === null)->count();
        $this->add('super_admin', $without === 0 ? 'ok' : ($live ? 'fail' : 'warn'), $without === 0 ? $admins->count().' Super Admin(s), all with two-factor' : "{$without} Super Admin(s) without two-factor", 'Sign in as each Super Admin and turn on two-factor (Account > Security)');
        $path = SuperAdminAccess::path();
        $this->add('super_admin_path', $path === 'super' ? ($live ? 'fail' : 'warn') : 'ok', $path === 'super' ? 'The Super Admin address is still the default /super' : 'The Super Admin address is your own', 'Set SUPER_ADMIN_PATH in .env to something hard to guess, then php artisan optimize (php artisan super:access shows it)');
        $default = $admins->contains(fn (User $u) => $u->email === 'admin@example.com');
        $this->add('default_admin', $default && $live ? 'fail' : 'ok', $default ? 'The demo Super Admin admin@example.com exists' : 'No demo Super Admin', 'Never run DemoSeeder on a live server; delete that account');
    }

    private function demoData(bool $live): void
    {
        $demo = Tenant::where('slug', 'demo')->exists() && User::where('email', 'demo@example.com')->exists();
        $this->add('demo_data', $demo && $live ? 'warn' : 'ok', $demo ? 'Demo restaurants are present' : 'No demo data', 'Fine for a sales demo; otherwise start from an empty database');
    }

    private function mail(bool $live, bool $quick): void
    {
        $stored = MailSettings::stored();
        $mailer = (string) config('mail.default');
        $real = $stored !== null || ! in_array($mailer, ['log', 'array'], true);
        $this->add('mail', $real || ! $live ? 'ok' : 'fail', $stored !== null ? 'E-mail goes out via '.$stored['host'] : "E-mail driver: {$mailer}", 'Super Admin > Settings > Email (SMTP), then Send test email');

        $from = (string) ($stored['from_address'] ?? config('mail.from.address'));
        $domain = str_contains($from, '@') ? strtolower(substr($from, strrpos($from, '@') + 1)) : '';
        if ($quick) {
            return;
        }
        if ($domain === '' || in_array($domain, ['example.com', 'localhost'], true) || ! function_exists('dns_get_record')) {
            $this->add('mail_dns', $live ? 'warn' : 'ok', 'Sender address: '.($from ?: 'none'), 'Use an address on your own domain (e.g. hello@menu.example.com)');

            return;
        }
        $txt = fn (string $host) => array_map(fn (array $r) => (string) ($r['txt'] ?? ''), @dns_get_record($host, DNS_TXT) ?: []);
        $spf = array_filter($txt($domain), fn (string $t) => str_starts_with(strtolower($t), 'v=spf1'));
        $dmarc = array_filter($txt('_dmarc.'.$domain), fn (string $t) => str_starts_with(strtolower($t), 'v=dmarc1'));
        $missing = array_keys(array_filter(['SPF' => $spf === [], 'DMARC' => $dmarc === []]));
        $this->add('mail_dns', $missing === [] ? 'ok' : 'warn', $missing === [] ? "SPF and DMARC found for {$domain}" : 'No '.implode(' or ', $missing)." record for {$domain} (mail may land in spam)", 'Add the DNS records in docs/deploy.md section 7; DKIM comes from your mail provider');
    }

    private function disk(): void
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        if ($free === false || $total === false || $total <= 0) {
            $this->add('disk', 'warn', 'Free disk space unknown', null);

            return;
        }
        $percent = (int) floor($free * 100 / $total);
        $status = $free < 512 * 1048576 ? 'fail' : ($percent < 10 ? 'warn' : 'ok');
        $this->add('disk', $status, sprintf('%s GB free (%d %%)', number_format($free / 1073741824, 1), $percent), 'Free some space: old backups (Super Admin > Backups), storage/logs');
    }

    private static function bytes(string $v): int
    {
        $n = (int) $v;

        return match (strtoupper(substr(trim($v), -1))) {
            'G' => $n * 1073741824,
            'M' => $n * 1048576,
            'K' => $n * 1024,
            default => $n,
        };
    }

    private static function short(string $message): string
    {
        // Never print passwords from a DSN-like message.
        return mb_substr((string) preg_replace('/password=[^;\s]+/i', 'password=***', $message), 0, 160);
    }
}
