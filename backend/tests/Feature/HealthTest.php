<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('answers the health check', function () {
    $this->get('/up')->assertOk();
});

it('serves the home page with the platform name', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(config('app.name'));
});

it('runs every migration on MySQL', function () {
    expect(DB::connection()->getDriverName())->toBe('mysql');
    expect(Schema::hasTable('users'))->toBeTrue();
});
