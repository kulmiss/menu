<?php

namespace App\Console\Commands;

use App\Support\Push\Push;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every 15 minutes: warns the Super Admins' phones about a stuck queue,
 * many failed jobs or a nearly full disk — each problem at most every 6 hours
 * (QUESTIONS #189).
 */
class PushHealthCheck extends Command
{
    protected $signature = 'push:health {--disk-free-percent=10}';

    protected $description = 'Push system health warnings to Super Admins';

    public function handle(): int
    {
        $problems = [];
        if (Schema::hasTable('jobs')) {
            $stuck = DB::table('jobs')->where('available_at', '<', now()->subMinutes(15)->getTimestamp())->count();
            if ($stuck > 0) {
                $problems['queue'] = __('push.health.queue', ['count' => $stuck]);
            }
        }
        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();
            if ($failed >= 10) {
                $problems['failed'] = __('push.health.failed', ['count' => $failed]);
            }
        }
        $total = @disk_total_space(storage_path());
        $free = @disk_free_space(storage_path());
        if ($total && $free !== false && ($free / $total) * 100 < (float) $this->option('disk-free-percent')) {
            $problems['disk'] = __('push.health.disk', ['percent' => (int) round($free / $total * 100)]);
        }

        foreach ($problems as $kind => $text) {
            if (Cache::add("push:health:{$kind}", true, now()->addHours(6))) {
                Push::send(Push::superAdmins(), 'health_warning', __('push.health.title'), (string) $text, '/p/dashboard');
            }
        }
        $this->info(count($problems).' problem(s)');

        return self::SUCCESS;
    }
}
