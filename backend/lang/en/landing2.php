<?php

// Landing 2: the new home page design (owner, 2026-11-22). English and Amharic only
// (lang/am/landing2.php); every text can be changed in Super Admin → Landing 2.
// Plans and prices come from the database, the numbers from the database or the
// Super Admin, restaurants and payment methods from Super Admin → Landing 2.
return [
    'meta_title' => ':name: QR menus guests love to scan',
    'meta_description' => 'Put your menu on every phone. Guests scan a QR code and see your dishes. No app needed. Free to start.',
    'meta_description_paid' => 'Put your menu on every phone. Guests scan a QR code and see your dishes. No app needed.',
    'skip' => 'Skip to the content',
    'lang' => ['label' => 'Language', 'en' => 'English', 'am' => 'አማርኛ'],
    'nav' => ['label' => 'Main', 'how' => 'How it works', 'styles' => 'Styles', 'pricing' => 'Prices', 'faq' => 'Questions', 'signin' => 'Sign in'],
    // The main button (sign-up open), else "contact us".
    'create_account' => 'Create my free menu',
    'contact_whatsapp' => 'Contact us on WhatsApp',
    'contact_email' => 'Contact us by email',
    'contact_text' => 'Hello, I would like a menu on :name.',
    'sticky' => 'Start free',
    'hero' => [
        'kicker' => 'QR menu for restaurants and cafés',
        'title' => 'Menus guests love to scan.',
        'text' => 'Your menu opens in a second on any phone, in your colours and languages. Change a price and every table sees it at once.',
        'demo' => 'See a live menu',
        'phone_alt' => 'A restaurant menu open on a phone',
        'points' => ['No app for guests', 'Ready in minutes', 'Works on slow phones'],
    ],
    // 1. The style switcher in the hero.
    'switcher' => [
        'label' => 'Try a menu style',
        'shot_alt' => 'The :style menu style on a phone',
        'open' => 'Open this style’s demo',
    ],
    'style_names' => [
        'editorial' => 'Editorial',
        'grid' => 'Grid',
        'bistro' => 'Bistro',
        'minimal' => 'Minimal',
        'mesob' => 'Mesob',
        'lounge' => 'Night Lounge',
        'board' => 'Street Board',
        'arabic' => 'Arabic',
        'islamic' => 'Islamic',
        'somali' => 'Somali',
        'mono' => 'Black & White',
        'colour' => 'Colourful',
        'coffee' => 'Coffee House',
        'grill' => 'Grill & BBQ',
        'garden' => 'Garden Fresh',
        'coastal' => 'Coastal',
        'luxe' => 'Fine Dining',
        'burger' => 'Burger & Pizza',
        'injera' => 'Injera & Tibs',
        'juice' => 'Juice & Drinks',
    ],
    'problem' => [
        'title' => 'Paper menus cost you time and money',
        'text' => 'A QR menu fixes this. Change it once, and every phone shows it.',
        'items' => [
            'print' => ['title' => 'You print again and again', 'text' => 'Every new price means new paper menus.'],
            'old' => ['title' => 'Guests see old prices', 'text' => 'Then they are surprised by the bill.'],
            'wait' => ['title' => 'Guests wait for a menu', 'text' => 'Busy staff cannot bring menus fast.'],
        ],
    ],
    'how' => [
        'title' => 'Ready in three steps',
        'steps' => [
            ['title' => 'Add your dishes', 'text' => 'Type them in, or send us your paper menu. Add prices, photos and what is in each dish.'],
            ['title' => 'Pick a style', 'text' => 'Choose one of the menu styles and your brand colour. See it on a phone before you save.'],
            ['title' => 'Put the QR on the tables', 'text' => 'Print table cards and posters. Guests scan and read: no app, no account.'],
        ],
    ],
    // 2. "Change a price" animation.
    'price_demo' => [
        'title' => 'Change a price. Every table sees it.',
        'text' => 'No new prints. Type the new price, save, and the menu on every phone is right.',
        'dish' => 'Shiro tegabino',
        'old' => 'Br 70',
        'new' => 'Br 80',
        'toast' => 'Saved ✓ · every table sees it',
        'label' => 'Example: the price of a dish changes from Br 70 to Br 80, and every table sees the new price.',
    ],
    // 13. Real numbers.
    'numbers' => [
        'label' => 'Our numbers',
        'restaurants' => 'restaurants use it',
        'dishes' => 'dishes on menus',
        'views' => 'menu views last month',
        'source' => 'Source: :source, :date',
    ],
    'styles' => [
        'title' => ':count menu styles, A to :last',
        'text' => 'From a warm café to fine dining. Tap one to open its live demo.',
        'open' => 'opens its live demo',
        'shot_alt' => 'The :style menu style on a phone',
        'prev' => 'Earlier menu styles',
        'next' => 'More menu styles',
    ],
    // 9. The demo video.
    'video' => [
        'title' => 'See it in 30 seconds',
        'text' => 'Sign up, add a dish, pick a style, print the QR code, and a guest scans it.',
        'play' => 'Play the 30-second video',
        'poster_alt' => 'The first picture of the demo video',
        'no_video' => 'Your browser cannot play this video.',
    ],
    'compare' => [
        'title' => 'Paper menu or QR menu?',
        'subtitle' => 'See what changes from day one.',
        'before' => 'Paper menu',
        'after' => 'QR menu',
        'rows' => [
            ['A new price means a new print.', 'Change a price in seconds.'],
            ['Sold out? Tell every table.', 'Sold out? One tap hides the dish.'],
            ['No photos of your food.', 'Photos that make guests hungry.'],
            ['Menus get dirty and torn.', 'Clean on every phone, every day.'],
            ['Hard to share with friends.', 'Share one link anywhere.'],
        ],
    ],
    'qr' => [
        'title' => 'One QR code. Always the latest menu.',
        'text' => 'The code on the table never changes. Sold out, a new price or today\'s special: guests see it the moment you save.',
        'scan' => 'Scan to open the demo menu',
        'open' => 'Open the demo menu',
    ],
    // 11. Free QR poster.
    'poster' => [
        'title' => 'Make your free QR poster',
        'text' => 'Type your restaurant’s name and pick a colour. Download it after a quick free sign-up: the QR code then opens your own menu.',
        'name' => 'Restaurant name',
        'name_example' => 'Your restaurant',
        'colour' => 'Colour',
        'colours' => ['brand' => 'Brand colour', 'red' => 'Red', 'green' => 'Green', 'blue' => 'Blue', 'gold' => 'Gold', 'black' => 'Black'],
        'language' => 'Poster language',
        'scan' => 'Scan for our menu',
        'sample' => 'Example QR code',
        'preview' => 'Poster preview',
        'download' => 'Sign up free to download (PDF and PNG)',
        'note' => 'A4 and A5 PDF, and a PNG picture.',
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
    // 4. Real restaurants.
    'restaurants' => [
        'title' => 'Restaurants that use it',
        'text' => 'Real places, with their permission.',
    ],
    'pricing' => [
        'title' => 'Simple prices',
        'text' => 'Start free. Move up when you need more.',
        'popular' => 'Start here',
        // 6. Monthly / Yearly.
        'billing' => 'Pay',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'yearly_save' => '2 months free',
        'per_year' => '/ year',
        'months_free' => '{1} 1 month free|[2,*] :n months free',
        'per_month_then' => 'or :price / month',
        // Plan names when a plan has no Amharic name (key: the plan's slug).
        'plan_names' => ['free' => 'Free', 'plus' => 'Plus', 'pro' => 'Pro', 'business' => 'Business'],
        // 5. Payment methods.
        'pay_with' => 'Pay with:',
    ],
    'faq' => [
        'title' => 'Questions and answers',
        'items' => [
            'free' => ['q' => 'Is it really free?', 'a' => 'Yes. The Free plan has no time limit. You never need a card.'],
            'app' => ['q' => 'Do my customers need an app?', 'a' => 'No. They scan the QR code with the phone camera. Your menu opens at once.'],
            'prices' => ['q' => 'Can I change prices myself?', 'a' => 'Yes. Sign in, change the price and save. Every phone shows the new price.'],
            'branches' => ['q' => 'I have many branches. Does it work?', 'a' => 'Yes. Each branch gets its own link and QR codes. See the plans for how many.'],
            'print' => ['q' => 'Can I print my menu?', 'a' => 'Yes. Print QR posters, table cards and stickers. Some plans also make a PDF menu.'],
            'photos' => ['q' => 'Can I add photos of my dishes?', 'a' => 'Yes. Add photos from your phone. We make them small, so your menu opens quickly.'],
            'upgrade' => ['q' => 'How do I upgrade?', 'a' => 'Message us. Pay by mobile money or bank transfer. We switch your plan for you.'],
            'help' => ['q' => 'How do I get help?', 'a' => 'Message us any time. A real person will help you.'],
        ],
    ],
    'final' => [
        'title' => 'Your new menu can be live today.',
        'text' => 'Create it in minutes. Change it any time.',
    ],
];
