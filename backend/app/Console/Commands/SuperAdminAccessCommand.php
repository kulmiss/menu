<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Security\SuperAdminAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Where the Super Admin area is and who may reach it (QUESTIONS #215–#217),
 * and the way back in when the IP allow-list locked you out: `--clear-ips`
 * empties SUPER_ADMIN_IPS in .env and clears the cached settings.
 */
class SuperAdminAccessCommand extends Command
{
    protected $signature = 'super:access {--clear-ips : Turn the IP allow-list off (empties SUPER_ADMIN_IPS in .env)}';

    protected $description = 'Show the Super Admin address and IP allow-list, or turn the allow-list off';

    public function handle(): int
    {
        if ($this->option('clear-ips')) {
            return $this->clearIps();
        }

        $path = SuperAdminAccess::path();
        $this->line('Super Admin address: '.rtrim((string) config('app.url'), '/').SuperAdminAccess::url());
        $this->line('Sign-in page:        '.rtrim((string) config('app.url'), '/').SuperAdminAccess::url('sign-in'));
        $ips = SuperAdminAccess::ips();
        $this->line('IP allow-list:       '.($ips === [] ? 'off (any address)' : implode(', ', $ips)));

        $problems = [];
        if (preg_match('/^[a-z0-9_-]{1,64}$/', $path) !== 1) {
            $problems[] = 'SUPER_ADMIN_PATH may only use a-z, 0-9, - and _ (one part, no slashes).';
        }
        if ($path !== 'super' && in_array($path, config('menu.reserved_slugs'), true)) {
            $problems[] = "SUPER_ADMIN_PATH \"{$path}\" is already used by another page. Choose another one.";
        }
        if (Tenant::withTrashed()->where('slug', $path)->exists()) {
            $problems[] = "A restaurant menu already uses /{$path}. Choose another SUPER_ADMIN_PATH.";
        }
        if ($path === 'super') {
            $this->warn('Tip: the address is still the default "super". Set SUPER_ADMIN_PATH in .env to something hard to guess.');
        }
        foreach ($problems as $problem) {
            $this->error($problem);
        }
        $this->line('After changing .env: php artisan config:cache && php artisan route:cache');

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }

    private function clearIps(): int
    {
        $file = app()->environmentFilePath();
        if (! is_file($file) || ! is_writable($file)) {
            $this->error("Can't write {$file}. Empty SUPER_ADMIN_IPS there by hand.");

            return self::FAILURE;
        }
        $env = (string) file_get_contents($file);
        $env = preg_match('/^SUPER_ADMIN_IPS=.*$/m', $env) === 1
            ? (string) preg_replace('/^SUPER_ADMIN_IPS=.*$/m', 'SUPER_ADMIN_IPS=', $env)
            : rtrim($env, "\n")."\nSUPER_ADMIN_IPS=\n";
        file_put_contents($file, $env);
        Artisan::call('config:clear');
        config(['menu.super_admin.ips' => []]);
        $this->info('IP allow-list is off. Any address can reach the Super Admin sign-in again (password and two-factor still needed).');
        $this->line('When you use config:cache on this server, run it again now.');

        return self::SUCCESS;
    }
}
