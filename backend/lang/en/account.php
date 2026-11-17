<?php

/*
| The account pages (QUESTIONS #222): sign-in, two-factor code and the plain
| Super Admin sign-in. Very simple English, like the landing page.
*/
return [
    'skip' => 'Skip to the form',
    'meta_description' => 'Sign in or make your free QR code menu on :name. Your menu on every phone, changed in seconds.',
    'home' => 'Home',
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
            'expired' => 'That took too long. Please try again.',
        ],
        'password_changed' => 'Your password is changed. Sign in with the new one.',
        'welcome_password_set' => 'Your password is saved. Sign in to start.',
    ],

    'signup' => [
        'title' => 'Create your free menu',
        'title_paid' => 'Create your menu',
        'intro' => 'It takes one minute. No card needed.',
        'intro_paid' => 'It takes one minute.',
        'name' => 'Your name',
        'restaurant' => 'Restaurant name',
        'restaurant_hint' => 'We make your menu address from this name. You see it after you sign up.',
        'email' => 'Email',
        'password' => 'Password',
        'password_hint' => 'At least 10 characters. A few words together are easy to remember and hard to guess.',
        'strength' => 'Password strength:',
        'strength_levels' => ['weak' => 'weak', 'fair' => 'fair', 'good' => 'good', 'strong' => 'strong'],
        'terms' => 'I accept the :terms and the :privacy.',
        'terms_link' => 'Terms',
        'privacy_link' => 'Privacy Policy',
        'terms_plain' => 'I accept the Terms and the Privacy Policy.',
        'website' => 'Leave this field empty',
        'submit' => 'Create my free menu',
        'submit_paid' => 'Create my menu',
        'google_closed' => 'New accounts can\'t be made right now.',
        'too_many' => 'Too many tries. Please try again in an hour.',
    ],

    'closed' => [
        'title' => 'Sign-up is closed for now',
        'text' => 'We are not taking new restaurants on this page right now. Write to us and we will set up your menu.',
        'whatsapp' => 'Contact us on WhatsApp',
        'email' => 'Contact us by email',
        'message' => 'Hello! I would like a menu for my restaurant on :name.',
    ],

    'verify' => [
        'title' => 'Check your inbox',
        'text' => 'We sent a link to :email. Open it to confirm your email.',
        'text_public' => 'If this email is new to us, a link is on its way. Open it to confirm your email, then sign in.',
        'expires' => 'The link works for 24 hours. Can\'t find it? Look in your spam folder.',
        'resend' => 'Send the email again',
        'sent' => 'We sent a new link to :email.',
        'done' => 'I opened the link',
        'not_yet' => 'Your email is not confirmed yet. Open the link in the email first.',
        'change' => 'Wrong email? Change it',
        'new_email' => 'New email',
        'save_email' => 'Send the link to this email',
        'changed' => 'Email changed. We sent a new link to :email.',
        'menu_off' => 'Your menu goes online after you confirm your email.',
        'sign_out' => 'Sign out',
        'back_to_sign_in' => 'Back to sign in',
    ],

    'waiting' => [
        'title' => 'Thanks! We are checking your restaurant',
        'text' => 'Your email is confirmed. Before :restaurant goes online, our team takes a quick look. We will email you when it is ready.',
        'meanwhile' => 'You can already add your dishes, photos and opening hours.',
        'button' => 'Set up my menu',
    ],

    'forgot' => [
        'title' => 'Forgot your password?',
        'intro' => 'Type your email. We will send you a link to choose a new password.',
        'submit' => 'Send me the link',
        'sent' => 'If :email has an account, the link is on its way. Look in your inbox and spam folder.',
        'back' => 'Back to sign in',
    ],

    'reset' => [
        'title' => 'Choose a new password',
        'welcome_title' => 'Welcome! Choose your password',
        'welcome_intro' => 'Your restaurant is ready. Choose a password to start.',
        'intro' => 'For :email.',
        'password' => 'New password',
        'submit' => 'Save my password',
        'invalid' => 'This link is not complete or too old.',
        'new_link' => 'Get a new link',
    ],

    'invite' => [
        'title' => 'Join :restaurant',
        'intro' => ':restaurant invited :email to help with their menu as :role.',
        'roles' => ['owner' => 'Owner', 'manager' => 'Manager', 'staff' => 'Staff'],
        'accept' => 'Join :restaurant',
        'signed_in_as' => 'You are signed in as :email.',
        'wrong_account' => 'This invitation is for :invited. You are signed in as :email.',
        'switch' => 'Sign out and use another account',
        'create' => 'Make your account',
        'name' => 'Your name',
        'password' => 'Choose a password',
        'submit' => 'Join :restaurant',
        'have_account' => 'Already have an account with :email?',
        'invalid_title' => 'This invitation does not work',
        'invalid_text' => 'It may be too old, already used, or taken back. Ask the person who invited you to send a new one.',
    ],

    'google_finish' => [
        'title' => 'Almost done',
        'intro' => 'You are signing up with Google as :email.',
        'restaurant' => 'Restaurant name',
        'submit' => 'Create my free menu',
        'submit_paid' => 'Create my menu',
    ],

    // The Super Admin's own page: plain, not linked, not indexed (QUESTIONS #216).
    'super' => [
        'title' => 'Sign in',
        'intro' => 'Staff only.',
    ],
];
