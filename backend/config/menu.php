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

    // Interface + default menu language. English only for now (QUESTIONS #14, #15);
    // menu texts are stored per language, so adding one needs no data change.
    'locales' => ['en'],

    // Write a static copy of each guest menu page to public/menu-cache (PLAN.md §15.2.1).
    'static_pages' => (bool) env('MENU_STATIC_PAGES', true),

    'default_currency' => env('MENU_DEFAULT_CURRENCY', 'ETB'),

    /*
    | Allergens: the fixed EU-14 list (QUESTIONS #17). Names are translated
    | by the apps; the database stores these keys.
    */
    'allergens' => [
        'gluten', 'crustaceans', 'eggs', 'fish', 'peanuts', 'soy', 'milk',
        'nuts', 'celery', 'mustard', 'sesame', 'sulphites', 'lupin', 'molluscs',
    ],

    /*
    | Tags every restaurant gets, translated by the apps. Restaurants can add
    | their own (with a name per language) next to these.
    */
    'system_tags' => ['vegan', 'vegetarian', 'spicy', 'halal', 'gluten_free', 'new', 'popular'],

    /*
    | Plan limits (plans.limits JSON) checked when something is created.
    | A missing key or null means unlimited.
    */
    'limit_keys' => ['menus', 'items', 'branches', 'tables', 'languages', 'photos', 'members'],

    // Largest price accepted, in minor units (100 000 000 = 1 000 000.00).
    'max_price_minor' => 100_000_000,

    /*
    | Slugs a restaurant can never take: they collide with platform routes.
    */
    'reserved_slugs' => [
        'admin', 'api', 'app', 'assets', 'auth', 'build', 'css', 'dashboard', 'docs',
        'favicon.ico', 'help', 'img', 'images', 'js', 'login', 'logout', 'media',
        'menu', 'menu-cache', 'platform', 'privacy', 'q', 'register', 'robots.txt', 'sanctum',
        'settings', 'signup', 'sitemap.xml', 'static', 'sw.js', 'status', 'storage', 'support',
        'terms', 'up', 'www',
    ],
];
