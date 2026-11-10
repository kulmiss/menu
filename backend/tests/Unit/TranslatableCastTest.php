<?php

use App\Casts\Translatable;

it('drops blank translations and sorts by language', function () {
    expect(Translatable::clean(['en' => ' Tea ', 'am' => '', 'so' => null, 'ar' => 'شاي', 5 => 'x']))
        ->toBe(['ar' => 'شاي', 'en' => 'Tea']);
});
