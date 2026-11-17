<?php

/*
| The account pages (QUESTIONS #222): sign-in, two-factor code and the plain
| Super Admin sign-in. Very simple English, like the landing page.
*/
return [
    'skip' => 'Skip to the form',
    'home' => 'Home',
    'brand_home' => ':name home page',
    'create_account' => 'Create my free menu',
    'have_account' => 'Already have an account?',
    'no_account' => 'No account yet?',
    'sign_in' => 'Sign in',
    'sign_up_link' => 'Create a free account',
    'terms' => 'Terms',
    'privacy' => 'Privacy',
    'or' => 'or',
    'google' => 'Continue with Google',

    // Left side on desktop, below the form on phones.
    'aside' => [
        'title' => 'Your menu, on every phone',
        'points' => [
            'Guests scan a QR code and see your menu.',
            'Change a price and it shows at once.',
            'Mark a dish sold out with one tap.',
        ],
        'phone_alt' => 'A demo menu on a phone',
    ],

    'signin' => [
        'title' => 'Sign in',
        'intro' => 'Welcome back. Sign in to manage your menu.',
        'email' => 'Email',
        'email_placeholder' => 'name@example.com',
        'password' => 'Password',
        'forgot' => 'Forgot password?',
        'remember' => 'Keep me signed in on this device',
        'submit' => 'Sign in',
        'expired' => 'You were signed out. Please sign in again.',
    ],

    'show_password' => 'Show password',
    'hide_password' => 'Hide password',

    'two_factor' => [
        'title' => 'Enter your code',
        'intro' => 'Open your authenticator app and type the 6-digit code.',
        'code' => 'Code',
        'recovery_intro' => 'Type one of your recovery codes. Each code works once.',
        'recovery_code' => 'Recovery code',
        'use_recovery' => 'Use a recovery code instead',
        'use_code' => 'Use the code from your app instead',
        'submit' => 'Continue',
        'start_again' => 'Start again',
        'gone' => 'That took too long. Please sign in again.',
    ],

    // Where the e-mail links and "Continue with Google" sent the visitor.
    'notices' => [
        'verify' => [
            'done' => 'Your email is confirmed. Sign in to set up your menu.',
            'already' => 'Your email was already confirmed. Sign in to continue.',
            'expired' => 'This link is too old. Sign in and press "Send the email again".',
            'invalid' => 'This link does not work. Sign in and press "Send the email again".',
            'change_done' => 'Your new email is confirmed. Use it to sign in from now on.',
            'change_expired' => 'This link is too old. Sign in with your old email and change it again in Settings.',
            'change_invalid' => 'This link does not work any more. Sign in with your old email and change it again in Settings.',
        ],
        'google' => [
            'cancelled' => 'Google sign-in was stopped. Try again, or use your email and password.',
            'failed' => 'Google sign-in did not work. Please try again.',
            'unverified' => 'Google has not confirmed the email of this Google account. Use another account, or your email and password.',
            'super_admin' => 'This account can\'t use Google here.',
        ],
        'password_changed' => 'Your password is changed. Sign in with the new one.',
        'welcome_password_set' => 'Your password is saved. Sign in to start.',
    ],

    // The Super Admin's own page: plain, not linked, not indexed (QUESTIONS #216).
    'super' => [
        'title' => 'Sign in',
        'intro' => 'Staff only.',
    ],
];
