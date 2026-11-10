<?php

arch('no debugging calls are left in the code')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('application code uses strict PHP')
    ->preset()
    ->php();
