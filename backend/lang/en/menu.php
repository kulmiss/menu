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
        'mail_subject' => 'You are invited to :restaurant',
        'mail_line' => ':inviter invited you to help manage :restaurant as :role.',
        'mail_action' => 'Accept invitation',
        'mail_expiry' => 'This invitation expires on :date.',
    ],
    'roles' => [
        'owner' => 'Owner',
        'manager' => 'Manager',
        'staff' => 'Staff',
    ],
];
