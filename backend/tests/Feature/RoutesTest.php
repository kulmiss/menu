<?php

it('requires an Idempotency-Key on every create/change API route', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
        ->filter(fn ($route) => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== []);

    expect($routes)->not->toBeEmpty();

    $missing = $routes
        ->reject(fn ($route) => in_array('idempotent', $route->gatherMiddleware(), true))
        // The guest-page visit beacon only counts; replaying it is harmless.
        ->reject(fn ($route) => $route->uri() === 'api/v1/beacon')
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()->all();

    expect($missing)->toBe([]);
});
