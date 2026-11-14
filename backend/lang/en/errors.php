<?php

// Friendly error pages (resources/views/errors).
return [
    'home' => 'Go to the home page',
    403 => ['title' => 'No access', 'text' => 'You don’t have permission to open this page.'],
    404 => ['title' => 'Page not found', 'text' => 'This page doesn’t exist or has moved. If you scanned a QR code, ask the staff for the menu.'],
    419 => ['title' => 'Page expired', 'text' => 'This page was open for too long. Go back, refresh and try again.'],
    429 => ['title' => 'Too many requests', 'text' => 'Please wait a minute and try again.'],
    500 => ['title' => 'Something went wrong', 'text' => 'We couldn’t load this page. Please try again in a moment.'],
    503 => ['title' => 'Back soon', 'text' => 'We’re updating the service. Please try again in a few minutes.'],
];
