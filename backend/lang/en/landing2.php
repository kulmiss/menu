<?php

// Landing 2: the new home page design (owner, 2026-11-22). Plans, prices, FAQ answers and
// the main button come from the same live data as Landing 1.
return [
    'meta_title' => ':name: QR menus guests love to scan',
    'skip' => 'Skip to the content',
    'nav' => ['label' => 'Main', 'how' => 'How it works', 'styles' => 'Styles', 'pricing' => 'Prices', 'faq' => 'Questions', 'signin' => 'Sign in'],
    'hero' => [
        'kicker' => 'QR menu for restaurants and cafés',
        'title' => 'Menus guests love to scan.',
        'text' => 'Your menu opens in a second on any phone, in your colours and languages. Change a price and every table sees it at once.',
        'demo' => 'See a live menu',
        'phone_alt' => 'A restaurant menu open on a phone',
        'points' => ['No app for guests', 'Ready in minutes', 'Works on slow phones'],
    ],
    'how' => [
        'title' => 'Ready in three steps',
        'steps' => [
            ['title' => 'Add your dishes', 'text' => 'Type them in, or send us your paper menu. Add prices, photos and what is in each dish.'],
            ['title' => 'Pick a style', 'text' => 'Choose one of the menu styles and your brand colour. See it on a phone before you save.'],
            ['title' => 'Put the QR on the tables', 'text' => 'Print table cards and posters. Guests scan and read: no app, no account.'],
        ],
    ],
    'styles' => [
        'title' => ':count menu styles, A to :last',
        'text' => 'From a warm café to fine dining. Tap one to open its live demo.',
        'open' => 'Open the :style demo',
        'shot_alt' => 'The :style menu style on a phone',
    ],
    'qr' => [
        'title' => 'One QR code. Always the latest menu.',
        'text' => 'The code on the table never changes. Sold out, a new price or today\'s special: guests see it the moment you save.',
        'scan' => 'Scan to open the demo menu',
    ],
    'features' => [
        'title' => 'Made for busy restaurants',
        'items' => [
            'order' => ['title' => '"My order" list', 'text' => 'Guests add dishes to a list on their phone and show it to the waiter. No payment, no account.'],
            'languages' => ['title' => 'Your languages', 'text' => 'Write the menu in more than one language. Right-to-left styles for Arabic menus.'],
            'fast' => ['title' => 'Fast on any phone', 'text' => 'The menu is a small, light page: it opens quickly even on older phones and slow networks.'],
            'insights' => ['title' => 'See what guests look at', 'text' => 'Menu views, QR scans and the most viewed dishes, counted without tracking anyone.'],
        ],
    ],
    'pricing' => [
        'title' => 'Simple prices',
        'text' => 'Start free. Move up when you need more.',
        'popular' => 'Start here',
    ],
    'final' => [
        'title' => 'Your new menu can be live today.',
        'text' => 'Create it in minutes. Change it any time.',
    ],
    'sticky' => 'Start free',
];
