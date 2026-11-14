<?php

// Super Admin → Backups (owner, 2026-11-13).
return [
    'problem_failed' => 'The last backup failed: :error',
    'problem_none' => 'No backup succeeded in the last :hours hours.',
    'wrong_password' => 'The password or code is not correct.',
    'not_ready' => 'This backup is not available for download.',
    'key_invalid' => 'BACKUP_KEY in .env is not valid. Make a new one with "php artisan backup:key".',
    'offsite_needs_key' => 'Not copied off-site: backups are not encrypted. Add BACKUP_KEY to .env ("php artisan backup:key"), then back up again.',
    'mail' => [
        'subject' => 'Backup problem on :platform',
        'greeting' => 'A backup needs your attention',
        'line' => 'Open Backups to see the details, check the free disk space, and press "Back up now".',
        'action' => 'Open Backups',
    ],
];
