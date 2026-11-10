<?php

use Tests\TestCase;

uses(TestCase::class);

it('serves public files from public/media without a symlink', function () {
    expect(config('filesystems.disks.public.root'))->toBe(public_path('media'))
        ->and(config('filesystems.links'))->toBe([]);
});
