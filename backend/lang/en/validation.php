<?php

/*
| Simpler answers for the fields of the account pages (QUESTIONS #222). Only
| these lines; every other message is Laravel's own (the framework's file is
| merged under this one).
*/
return [
    'custom' => [
        'name' => ['required' => 'Please type your name.'],
        'restaurant_name' => ['required' => 'Please type the name of your restaurant.'],
        'email' => [
            'required' => 'Please type your email.',
            'email' => 'Type a real email, like name@example.com.',
        ],
        'password' => ['required' => 'Please choose a password.'],
        'terms' => ['accepted' => 'Please tick this box to go on.'],
    ],
    'attributes' => [
        'restaurant_name' => 'restaurant name',
    ],
];
