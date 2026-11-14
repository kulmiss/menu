<?php

use Laravel\Fortify\Features;

/*
| Fortify runs headless: the SPA renders every screen and talks to these
| JSON endpoints under /api/v1/auth. See PLAN.md §3.1.
*/
return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,

    // Where Fortify would redirect after login; the SPA ignores it (JSON responses).
    'home' => '/admin',

    'prefix' => 'api/v1/auth',
    'domain' => null,
    'middleware' => ['web', 'idempotent', 'password.throttle'],

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],

    'views' => false,

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],
];
