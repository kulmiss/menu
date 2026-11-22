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

    // Menu style of the demo restaurant (CI measures each style).
    'demo_style' => env('DEMO_MENU_STYLE', 'grid'),

    // Banner on the demo restaurant (CI also measures every style without one).
    'demo_banner' => (bool) env('DEMO_MENU_BANNER', true),

    // Specials on the demo restaurant (CI measures style A without banner and specials).
    'demo_specials' => (bool) env('DEMO_MENU_SPECIALS', true),

    // Demo happy hour running all day (CI measures a page during a price period).
    'demo_price_period' => (bool) env('DEMO_MENU_PRICE_PERIOD', false),

    // Style options on the demo restaurant, comma-separated (CI measures them):
    // rtl (H · Arabic right-to-left), halal and ramadan (I), greyscale and accent (K).
    'demo_options' => array_values(array_filter(explode(',', (string) env('DEMO_MENU_OPTIONS', '')))),

    // Where the demo seeders find the downloaded demo photos (php artisan demo:photos).
    'demo_photos_path' => env('DEMO_PHOTOS_PATH'),

    'default_currency' => env('MENU_DEFAULT_CURRENCY', 'ETB'),

    // Currencies a restaurant can pick when it signs up (ISO 4217).
    'currencies' => ['ETB', 'KES', 'SOS', 'DJF', 'UGX', 'TZS', 'RWF', 'SDG', 'ERN', 'AED', 'SAR', 'USD', 'EUR', 'GBP'],

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
    | The Super Admin area (QUESTIONS #215–#217). `path`: its web address,
    | /{path} (letters, digits, - and _; never a menu address). `ips`: when not
    | empty, only these addresses (comma-separated, IPv4/IPv6, CIDR allowed)
    | reach the Super Admin pages, its sign-in and its API; others get a 404.
    | Both live in .env, not the database, so cached routes keep working and a
    | database change can't move or open the area. `php artisan super:access`.
    */
    'super_admin' => [
        'path' => strtolower(trim((string) env('SUPER_ADMIN_PATH', 'super'), '/ ')) ?: 'super',
        'ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('SUPER_ADMIN_IPS', ''))))),
    ],

    /*
    | Slugs a restaurant can never take: they collide with platform routes.
    */
    'reserved_slugs' => [
        'about', 'admin', 'am', 'api', 'app', 'assets', 'auth', 'build', 'contact', 'cookies', 'css', 'dashboard', 'docs', 'email',
        'favicon.ico', 'forgot-password', 'health', 'help', 'invite', 'img', 'images', 'js', 'landing2', 'login', 'logout', 'media',
        'menu', 'menu-cache', 'phone', 'platform', 'privacy', 'q', 'register', 'reset-password', 'robots.txt', 'sanctum',
        'settings', 'signin', 'signout', 'signup', 'sitemap.xml', 'static', 'sw.js', 'status', 'storage', 'super', 'support',
        'terms', 'up', 'verify-email', 'waiting', 'www',
    ],
];
