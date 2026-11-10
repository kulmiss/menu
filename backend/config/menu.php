<?php

return [
    /*
    | Days a new restaurant can use the platform before a payment is needed.
    | Plans can override this with their own trial_days.
    */
    'trial_days' => (int) env('MENU_TRIAL_DAYS', 14),

    // Subscription timeline after expiry (see PLAN.md §4.2).
    'grace_days' => 7,
    'read_only_days' => 14,

    'invitation_days' => 7,

    'idempotency_hours' => 24,

    'locales' => ['en', 'so', 'am', 'ar'],

    'default_currency' => env('MENU_DEFAULT_CURRENCY', 'ETB'),

    /*
    | Slugs a restaurant can never take: they collide with platform routes.
    */
    'reserved_slugs' => [
        'admin', 'api', 'app', 'assets', 'auth', 'build', 'css', 'dashboard', 'docs',
        'favicon.ico', 'help', 'img', 'images', 'js', 'login', 'logout', 'media',
        'menu', 'platform', 'privacy', 'q', 'register', 'robots.txt', 'sanctum',
        'settings', 'signup', 'sitemap.xml', 'static', 'status', 'storage', 'support',
        'terms', 'up', 'www',
    ],
];
