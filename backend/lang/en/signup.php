<?php

// Self sign-up (owner, 2026-11-13).
return [
    'closed' => 'Registration is closed at the moment.',
    'email_not_allowed' => 'Please use another e-mail address.',
    'email_unavailable' => 'This e-mail address can\'t be used. If you already have an account, sign in or reset your password.',
    'too_many' => 'Too many attempts. Please wait a few minutes and try again.',
    'captcha' => 'Please confirm you are not a robot.',
    'unverified' => 'Please confirm your e-mail address first. We sent you a link.',
    'not_pending' => 'This restaurant isn\'t waiting for approval.',
    'turnstile_keys_needed' => 'Enter the Turnstile site key and secret key first.',
    'mail' => [
        'hello' => 'Hello,',
        'hello_name' => 'Hello :name,',
        'signature' => 'Thank you, the :app team',
        'help' => 'Questions? Write to :email.',
        'menu_address' => 'Your menu address: :url',
        'first_steps' => 'Three things to do first:',
        'step_look' => 'Upload your logo and a banner photo.',
        'step_dishes' => 'Add your first dishes and your opening hours.',
        'step_qr' => 'Print your QR codes and put them on the tables.',
        'verify' => [
            'subject' => 'Confirm your e-mail address for :app',
            'intro' => 'Thanks for signing up to :app! Please confirm your e-mail address to open your account.',
            'action' => 'Confirm my e-mail',
            'expiry' => 'The link works for :hours hours. If it has expired, sign in and send a new one.',
            'not_you' => 'If you didn\'t sign up, you can ignore this e-mail; nothing will happen.',
        ],
        'welcome' => [
            'subject' => 'Welcome to :app — :restaurant is ready',
            'intro' => 'Your e-mail is confirmed and :restaurant is set up on :app. Your menu goes live as you add dishes.',
            'action' => 'Open my admin',
        ],
        'pending' => [
            'subject' => 'Thanks — we\'re reviewing :restaurant',
            'intro' => 'Your e-mail is confirmed. Before :restaurant goes live, the :app team takes a quick look. We\'ll e-mail you as soon as it\'s approved.',
            'meanwhile' => 'Meanwhile you can already add your dishes, photos and opening hours.',
            'action' => 'Set up my menu',
        ],
        'approved' => [
            'subject' => ':restaurant is approved',
            'intro' => 'Good news: :restaurant is approved and your menu is live.',
            'action' => 'Open my admin',
        ],
        'rejected' => [
            'subject' => 'About your :app sign-up',
            'intro' => 'Thank you for your interest in :app. We can\'t approve :restaurant at the moment, so the account has been removed.',
            'reason' => 'Reason: :reason',
            'outro' => 'If you think this is a mistake, simply reply to this e-mail.',
        ],
        'admin' => [
            'subject' => 'New sign-up: :restaurant',
            'intro' => ':restaurant just signed up and confirmed the e-mail address.',
            'owner' => 'Owner: :owner (:email)',
            'live' => 'It is live on the sign-up plan.',
            'waiting' => 'It is waiting for your approval.',
            'action' => 'Open in the platform',
        ],
        'existing' => [
            'subject' => 'Sign-up attempt with your e-mail on :app',
            'intro' => 'Someone (maybe you) tried to create a new :app account with this e-mail address. You already have an account, so nothing was created.',
            'action' => 'Sign in',
            'forgot' => 'Forgot your password? Use "Forgot password" on the sign-in page.',
            'not_you' => 'If this wasn\'t you, you can ignore this e-mail.',
        ],
    ],
];
