<?php

// E-mails to a restaurant's owners when it is blocked, suspended, or put back
// (owner, 2026-11-25). :reason is shown only for blocked and suspended.
return [
    'mail' => [
        'hello' => 'Hello,',
        'hello_name' => 'Hello :name,',
        'reason' => 'Reason given: :reason',
        'help' => 'If you have questions, email us at :email.',
        'open' => 'Open your admin',
        'signature' => 'The :app team',

        'blocked' => [
            'subject' => ':restaurant has been blocked',
            'intro' => 'Your restaurant ":restaurant" has been blocked on :app. For now your admin is turned off and your public menu is not reachable.',
            'outro' => 'Please get in touch so we can sort this out together.',
        ],
        'suspended' => [
            'subject' => ':restaurant has been suspended',
            'intro' => 'Your restaurant ":restaurant" has been suspended on :app. For now your menu shows as unavailable and changes are paused.',
            'outro' => 'Please get in touch so we can sort this out together.',
        ],
        'unblocked' => [
            'subject' => ':restaurant is active again',
            'intro' => 'Good news — the block on ":restaurant" has been lifted. Your admin and your public menu are back to normal.',
            'outro' => 'Thanks for your patience.',
        ],
        'unsuspended' => [
            'subject' => ':restaurant is active again',
            'intro' => 'Good news — the suspension on ":restaurant" has been lifted. Your menu is live again.',
            'outro' => 'Thanks for your patience.',
        ],
    ],
];
