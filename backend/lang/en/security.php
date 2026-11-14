<?php

return [
    'reset_link_generic' => 'If an account uses this e-mail address, we have sent it a link to choose a new password. Check your inbox and spam folder.',
    'email_change' => [
        'password' => 'Enter your current password to change your e-mail address.',
        'sent' => 'We sent a link to :email. Your address changes when you click it.',
        'confirm' => [
            'subject' => 'Confirm your new e-mail address for :app',
            'intro' => 'You asked to use this address for your :app account. Click the button to confirm it.',
            'action' => 'Confirm new address',
            'expiry' => 'The link works for :minutes minutes. Until then you keep signing in with your old address.',
            'not_you' => 'If you didn\'t ask for this, ignore this e-mail; nothing changes.',
        ],
        'notice' => [
            'subject' => 'Your :app e-mail address is being changed',
            'intro' => 'Someone signed in to your :app account asked to change its e-mail address to :email.',
            'keep' => 'Nothing changes until the new address is confirmed.',
            'not_you' => 'Not you? Sign in, change your password and turn on two-factor sign-in. Your address stays the same as long as nobody clicks the link sent to the new one.',
            'action' => 'Secure my account',
        ],
    ],
];
