<?php

return [
    'types' => [
        'plan_expiring' => 'Plan ending soon',
        'plan_expired' => 'Plan ended',
        'monthly_report' => 'Monthly report ready',
        'invitation_accepted' => 'Team invitation accepted',
        'platform_announcement' => 'News from the platform',
        'signup_new' => 'New sign-up',
        'signup_pending' => 'Sign-up waiting for approval',
        'payment_recorded' => 'Payment recorded',
        'backup_failed' => 'Backup problem',
        'health_warning' => 'System health warning',
        'followups_due' => 'Follow-ups due',
    ],
    'plan_expiring' => ['title' => 'Your plan ends in :days day(s)', 'body' => ':restaurant: renew to keep your menu online without interruption.'],
    'plan_expired' => ['title' => 'Your plan has ended', 'body' => ':restaurant: renew now to keep editing your menu.'],
    'monthly_report' => ['title' => 'Your :month report is ready', 'body' => ':restaurant: see how many guests opened your menu.'],
    'invitation_accepted' => ['title' => ':name joined your team', 'body' => ':name is now :role at :restaurant.'],
    'signup_new' => ['title' => 'New restaurant: :restaurant', 'body' => ':owner (:email) confirmed their e-mail.'],
    'signup_pending' => ['title' => 'Approve :restaurant?', 'body' => ':owner (:email) is waiting for approval.'],
    'payment_recorded' => ['title' => 'Payment: :restaurant', 'body' => ':amount recorded by :by.'],
    'followups_due' => ['title' => ':count follow-up(s) due today', 'body' => 'Restaurants you wanted to contact today.'],
    'health' => [
        'title' => 'System health warning',
        'queue' => ':count queued job(s) are waiting more than 15 minutes. Is the cron job running?',
        'failed' => ':count job(s) failed in the last hour.',
        'disk' => 'Only :percent% disk space is free.',
    ],
];
