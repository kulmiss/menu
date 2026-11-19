<?php

return [
    'too_many' => 'You already sent 3 menus we are still working on. We will tell you when they are done.',
    'type' => 'Send photos (JPG, PNG or WebP) or a PDF.',
    'mail' => [
        'subject' => ':restaurant asks you to add their menu',
        'line' => '{1} :restaurant sent 1 photo of their menu and asks you to type it in.|[2,*] :restaurant sent :count photos of their menu and asks you to type it in.',
        'action' => 'Open the request',
        'done_subject' => 'Your menu is ready',
        'done_line' => 'We added your menu for :restaurant. Have a look and change anything you like.',
        'done_action' => 'Open my menu',
    ],
];
