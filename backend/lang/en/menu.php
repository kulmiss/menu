<?php

// English source strings for the API. Other languages: lang/{so,am,ar}/menu.php (Partition 8).
return [
    'idempotency' => [
        'missing' => 'This request needs an Idempotency-Key header.',
        'invalid' => 'The Idempotency-Key header is not valid.',
        'mismatch' => 'This Idempotency-Key was already used for a different request.',
        'in_progress' => 'This request is still being processed. Please try again in a moment.',
    ],
    'subscription' => [
        'read_only' => 'Your subscription has expired, so changes are paused. Renew to edit your menu again.',
    ],
    'slug' => [
        'format' => 'Use 3–50 lower-case letters, numbers and single hyphens.',
        'reserved' => 'This address is reserved. Please choose another one.',
        'taken' => 'This address is already taken.',
    ],
    'team' => [
        'already_member' => 'This person is already on your team.',
        'cannot_change_self' => 'You cannot change your own role.',
        'owner_cannot_leave' => 'The owner cannot leave the restaurant.',
    ],
    'invitation' => [
        'wrong_account' => 'This invitation was sent to a different email address.',
        'invalid' => 'This invitation is no longer valid. Ask for a new one.',
        'mail_subject' => 'You are invited to :restaurant',
        'mail_line' => ':inviter invited you to help manage :restaurant as :role.',
        'mail_action' => 'Accept invitation',
        'mail_expiry' => 'This invitation expires on :date.',
    ],
    'limits' => [
        'reached' => 'Your plan allows :limit :what. Upgrade your plan to add more.',
        'menus' => 'menus',
        'items' => 'items',
        'branches' => 'branches',
        'languages' => 'languages',
        'photos' => 'photos',
        'members' => 'team members',
    ],
    'translations' => [
        'format' => 'Enter the text per language.',
        'too_long' => 'Use at most :max characters.',
        'default_missing' => 'Fill in the main language (:language).',
    ],
    'menu_editor' => [
        'copy_suffix' => ':name (copy)',
        'reorder_mismatch' => 'The list changed in the meantime. Reload and try again.',
        'one_default_variant' => 'Mark only one size as the default.',
        'max_below_min' => 'The maximum cannot be lower than the minimum.',
        'system_tag' => 'Built-in tags cannot be changed or deleted.',
        'wrong_menu' => 'This category belongs to another menu.',
        'empty_window' => 'The end time must differ from the start time.',
    ],
    'media' => [
        'type' => 'Upload a JPEG, PNG or WebP photo.',
        'heic' => 'iPhone HEIC photos cannot be used yet. In Settings › Camera › Formats choose "Most Compatible", or share the photo as JPEG.',
        'unreadable' => 'This file could not be read as a photo.',
    ],
    'spa' => [
        'not_built' => 'The admin app has not been built yet. Run the frontend build.',
    ],
    'roles' => [
        'owner' => 'Owner',
        'manager' => 'Manager',
        'staff' => 'Staff',
    ],
];
