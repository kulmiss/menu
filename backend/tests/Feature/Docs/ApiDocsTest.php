<?php

use App\Console\Commands\ApiReferenceCommand;
use Illuminate\Routing\Router;

/**
 * The API docs can't fall behind the routes (owner, 2026-11-16):
 * docs/api-reference.md is generated, and every app-only or app-control
 * endpoint has a section with an example in docs/mobile-api.md.
 */
function mobileApiDoc(): string
{
    return (string) file_get_contents(dirname(base_path()).'/docs/mobile-api.md');
}

/** `### `GET /api/v1/x` · `POST /api/v1/y`` → ['GET x' => section text, …] */
function mobileApiSections(): array
{
    $sections = [];
    foreach (preg_split('/^(?=#{2,3} )/m', mobileApiDoc()) as $part) {
        $heading = strtok($part, "\n");
        preg_match_all('/`(GET|POST|PUT|PATCH|DELETE) \/api\/v1\/([^`]+)`/', (string) $heading, $m, PREG_SET_ORDER);
        foreach ($m as [, $method, $uri]) {
            $sections["$method $uri"] = $part;
        }
    }

    return $sections;
}

/** Routes the app uses that the web admin doesn't, and the Super Admin screens that control the app. */
function appRoutes(): array
{
    $keep = fn (string $uri) => str_starts_with($uri, 'mobile/')
        || str_starts_with($uri, 'platform/mobile-app')
        || in_array($uri, ['me', 'me/onboarding', 'help/public', 'help/feedback', 't/{tenant}/help', 'impersonation/stop', 'platform/dashboard', 'platform/settings', 'platform/settings/features', 'platform/restaurants/{restaurant}/impersonate-token'], true);
    $out = [];
    foreach (ApiReferenceCommand::rows(app(Router::class)) as $r) {
        if ($keep($r['uri'])) {
            foreach (explode('|', $r['method']) as $method) {
                $out[] = "$method {$r['uri']}";
            }
        }
    }

    return $out;
}

it('keeps docs/api-reference.md the same as the routes', function () {
    expect(file_get_contents(ApiReferenceCommand::path()))
        ->toBe(ApiReferenceCommand::render(app(Router::class)), 'Routes changed: run `php artisan docs:api-reference` and commit docs/api-reference.md.');
});

it('has a section in docs/mobile-api.md for every app endpoint', function () {
    $missing = array_values(array_diff(appRoutes(), array_keys(mobileApiSections())));
    expect($missing)->toBe([], 'Add a "### `METHOD /api/v1/path`" section with an example to docs/mobile-api.md for: '.implode(', ', $missing));
});

it('gives every section a request and an answer example', function () {
    foreach (mobileApiSections() as $endpoint => $text) {
        expect(str_contains($text, '```http'))->toBeTrue("$endpoint: add a request example");
        expect(str_contains($text, '```json') || str_contains($text, 'No Content') || str_contains($text, 'Answer:'))
            ->toBeTrue("$endpoint: add an answer example");
    }
});

it('documents only endpoints that exist', function () {
    $routes = [];
    foreach (ApiReferenceCommand::rows(app(Router::class)) as $r) {
        foreach (explode('|', $r['method']) as $method) {
            $routes[] = "$method {$r['uri']}";
        }
    }
    $unknown = array_values(array_diff(array_keys(mobileApiSections()), $routes));
    expect($unknown)->toBe([], 'docs/mobile-api.md names endpoints that do not exist: '.implode(', ', $unknown));
});

it('lists every error code the app reacts to', function () {
    foreach (['mobile.update_required', 'mobile.maintenance', 'mobile.app_off', 'features.', 'mobile.token_required', 'mobile.challenge_expired', 'impersonation.blocked'] as $code) {
        expect(mobileApiDoc())->toContain($code);
    }
});
