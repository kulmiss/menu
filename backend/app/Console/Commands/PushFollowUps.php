<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Push\Push;
use Illuminate\Console\Command;

/** Daily 08:00: "N follow-ups due today" to the Super Admins (QUESTIONS #189). */
class PushFollowUps extends Command
{
    protected $signature = 'push:followups';

    protected $description = 'Push the number of restaurant follow-ups due today to Super Admins';

    public function handle(): int
    {
        $count = Tenant::query()->whereNotNull('follow_up_on')->whereDate('follow_up_on', '<=', now()->toDateString())->count();
        if ($count > 0) {
            Push::send(Push::superAdmins(), 'followups_due',
                __('push.followups_due.title', ['count' => $count]), __('push.followups_due.body'),
                '/p/restaurants?follow_up=due');
        }
        $this->info("Follow-ups due: {$count}");

        return self::SUCCESS;
    }
}
