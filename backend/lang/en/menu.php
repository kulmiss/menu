<?php

// English source strings for the API. Other languages: lang/{so,am,ar}/menu.php (Partition 8).
return [
    'price_period' => [
        'percent' => 'A discount can be at most 90%.',
        'size' => 'This size belongs to another dish.',
    ],
    'idempotency' => [
        'missing' => 'This request needs an Idempotency-Key header.',
        'invalid' => 'The Idempotency-Key header is not valid.',
        'mismatch' => 'This Idempotency-Key was already used for a different request.',
        'in_progress' => 'This request is still being processed. Please try again in a moment.',
        'not_replayable' => 'This request was already done. Its answer is not kept for security; please reload.',
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
        'tables' => 'tables',
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
        'too_many_pixels' => 'This photo is too large to process (over 50 megapixels). Use a normal phone photo, or make it smaller first.',
    ],
    'import' => [
        'file_type' => 'Upload a .csv or .xlsx file.',
        'unreadable' => 'This file could not be read. Save it as CSV (UTF-8) or Excel .xlsx and try again.',
        'too_many' => 'Import at most :max rows at a time.',
        'missing_columns' => 'The first row needs these column names: :columns.',
        'required' => 'The :column column is empty.',
        'too_long' => 'The :column text is longer than :max characters.',
        'bad_price' => '":value" is not a price. Use a number like 120 or 120.50.',
        'bad_size' => 'Write sizes like "Small=60; Large=80" (problem: ":value").',
        'bad_allergen' => '":value" is not one of the 14 allergens.',
        'bad_yes_no' => 'Use yes or no in :column (found ":value").',
        'bad_number' => 'Use a whole number in :column (found ":value").',
        'duplicate_row' => 'The same item is already on line :line.',
        'expired' => 'This preview has expired. Upload the file again.',
        'has_errors' => 'Fix the rows with errors and upload the file again.',
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
