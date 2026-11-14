<?php

return [
    'owner_two_factor_required' => 'Turn on two-factor sign-in first: your platform requires it for restaurant owners.',
    'two_factor_reminder' => [
        'subject' => 'Protect your :app account with two-factor sign-in',
        'intro' => 'Your restaurant\'s menu, prices and team are only as safe as your password. Two-factor sign-in adds a 6-digit code from your phone, so a stolen password alone is not enough.',
        'how' => 'It takes about two minutes: install a free authenticator app (Google Authenticator, Microsoft Authenticator, 2FAS…), press the button and scan the code.',
        'action' => 'Turn on two-factor sign-in',
        'once' => 'We send this reminder only once.',
    ],
    'password_common' => 'This password is too common or was found in data breaches. Choose a longer or less common one, for example three or four unrelated words.',
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
    'notice' => [
        'not_you' => 'If this wasn\'t you, reset your password now ("Forgot password" on the sign-in page) and turn on two-factor sign-in. That signs out every app.',
        'action' => 'Open security settings',
        'password_changed' => ['subject' => 'Your :app password was changed', 'line' => 'The password of your :app account was just changed. Other browsers and every app were signed out.'],
        'password_reset' => ['subject' => 'Your :app password was reset', 'line' => 'The password of your :app account was just reset with an e-mail link. Every browser and app was signed out.'],
        'two_factor_on' => ['subject' => 'Two-factor sign-in is on for :app', 'line' => 'Two-factor sign-in was just turned on for your :app account. Keep your recovery codes somewhere safe.'],
        'two_factor_off' => ['subject' => 'Two-factor sign-in was turned off for :app', 'line' => 'Two-factor sign-in was just turned OFF for your :app account. Signing in now needs only your password.'],
        'recovery_codes' => ['subject' => 'New :app recovery codes', 'line' => 'New two-factor recovery codes were just made for your :app account. The old ones no longer work.'],
        'new_device' => ['subject' => 'New sign-in to the :app app', 'line' => 'Your :app account was just signed in to the app on ":device" (:platform). You can sign out any device in the app under Account → Devices.'],
    ],
];
