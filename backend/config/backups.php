<?php

return [
    // Encrypts every backup zip (SECURITY_REPORT.md H4). Make one with `php artisan backup:key`
    // and keep a copy OUTSIDE the server (password manager): without it no backup can be restored.
    'key' => env('BACKUP_KEY'),
];
