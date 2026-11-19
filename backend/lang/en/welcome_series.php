<?php

// Welcome e-mails on day 1, 3 and 7 after sign-up (QUESTIONS #235). Simple English.
return [
    'step1' => [
        'subject' => 'Add your first dishes to :restaurant',
        'lines' => [
            'Your menu is ready for dishes. Most owners start with their 5 best sellers.',
            'Add a name, a price and a photo from your phone. Guests see the change at once.',
            'No time? Use "Add my menu for me": send photos of your paper menu and we type it in.',
        ],
        'action' => 'Add dishes',
        'path' => '/admin/menu',
    ],
    'step2' => [
        'subject' => 'Put :restaurant on the tables',
        'lines' => [
            'Your guests can open your menu with one scan.',
            'Download a table tent or stickers with your QR code, print them, and put one on every table.',
            'Tip: print a poster for the window too, so people outside can see your menu.',
        ],
        'action' => 'Print my QR codes',
        'path' => '/admin/print-materials',
    ],
    'step3' => [
        'subject' => 'Make :restaurant look like you',
        'lines' => [
            'Pick a menu style that fits your place: coffee house, grill, garden, coastal, fine dining and more.',
            'Add your logo and colour, and your opening hours, so guests see when you are open.',
            'After a few days, Insights shows how many guests opened your menu and which dishes they looked at.',
        ],
        'action' => 'Choose a style',
        'path' => '/admin/appearance',
    ],
    'unsubscribe' => 'Don\'t want these tips? [Stop them](:url) (account e-mails still come).',
];
