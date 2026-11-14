<?php

use App\Enums\Role;
use Illuminate\Routing\Route as RouteDef;
use Illuminate\Support\Facades\Route;

/**
 * Authorization matrix (SECURITY_REPORT.md section 3, M12, L13), built from the
 * route list itself, so a new route fails here until it is classified.
 *
 * guest → 401 everywhere but the public list · another restaurant's Owner → never
 * in · Owner → never in the platform · Staff → only the sold-out switches and
 * reading · Manager → never the Owner-only parts.
 */
const PUBLIC_ROUTES = [
    'GET /', 'GET Q/{code}', 'GET q/{code}', 'GET robots.txt', 'GET sitemap.xml', 'GET up', 'GET {page}', 'GET {area}/{path?}',
    'GET {slug}/{branch?}', 'GET {slug}/menu.pdf', 'GET {slug}/{branch}/menu.pdf',
    'POST api/v1/beacon', 'GET api/v1/invitations/{token}', 'GET api/v1/signup/config', 'GET api/v1/signup/slug', 'POST api/v1/signup',
    'POST api/v1/mobile/auth/login', 'POST api/v1/mobile/auth/two-factor', 'GET api/v1/mobile/config',
    'GET email/verify/{id}/{hash}', 'GET email/change/{user}/{hash}',
    'GET news/{user}/unsubscribe', 'POST news/{user}/unsubscribe', 'POST news/{user}/resubscribe',
    // Fortify: sign-in, sign-up from an invitation, password reset, e-mail verification link.
    'POST api/v1/auth/login', 'POST api/v1/auth/logout', 'POST api/v1/auth/register', 'POST api/v1/auth/forgot-password',
    'POST api/v1/auth/reset-password', 'POST api/v1/auth/two-factor-challenge', 'GET api/v1/auth/email/verify/{id}/{hash}',
    'GET sanctum/csrf-cookie', 'GET storage/{path}', 'PUT storage/{path}',
];

/** Staff (any member) may do these writes; every other tenant write needs Manager or Owner. */
const STAFF_WRITES = [
    'PATCH api/v1/t/{tenant}/items/{item}/sold-out', 'PATCH api/v1/t/{tenant}/variants/{variant}/sold-out',
    'PATCH api/v1/t/{tenant}/addon-options/{option}/sold-out', 'POST api/v1/t/{tenant}/announcements/{announcement}/read',
];

/** Owner only (Managers get 403). */
const OWNER_ONLY = [
    'GET api/v1/t/{tenant}/billing', 'GET api/v1/t/{tenant}/billing/invoices/{invoice}/pdf', 'PATCH api/v1/t/{tenant}/account/slug',
];

function matrixRoutes(): array
{
    $out = [];
    foreach (Route::getRoutes() as $route) {
        /** @var RouteDef $route */
        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
            $out[] = ['key' => $method.' '.$route->uri(), 'method' => $method, 'uri' => $route->uri(), 'mw' => $route->gatherMiddleware(), 'wheres' => $route->wheres];
        }
    }

    return $out;
}

/** A URL that matches the route: made-up ids, or the first allowed value of a constrained part. */
function matrixUrl(string $uri, int $tenantId, array $wheres = []): string
{
    $uri = str_replace('{tenant}', (string) $tenantId, $uri);

    return '/'.preg_replace_callback('/\{([^}?]+)\??\}/', function ($m) use ($wheres) {
        $where = $wheres[$m[1]] ?? null;
        if (is_string($where) && preg_match('/^\(?([a-z][a-z0-9_-]*)(\||\)|$)/i', $where, $first) === 1) {
            return $first[1];
        }

        return '999999';
    }, $uri);
}

function isProtected(array $mw): bool
{
    return collect($mw)->contains(fn ($m) => is_string($m) && (str_starts_with($m, 'auth') || $m === 'platform' || str_contains($m, 'Authenticate:')));
}

it('classifies every route: only the public list is open to guests', function () {
    $open = collect(matrixRoutes())->reject(fn ($r) => isProtected($r['mw']))->pluck('key')->unique()->sort()->values()->all();
    expect(array_values(array_diff($open, PUBLIC_ROUTES)))->toBe([]);
});

it('answers 401 to guests on every protected API route', function () {
    [, $tenant] = member(Role::Owner);
    $wrong = [];
    foreach (matrixRoutes() as $r) {
        if (! isProtected($r['mw']) || ! str_starts_with($r['uri'], 'api/')) {
            continue;
        }
        $status = $this->json($r['method'], matrixUrl($r['uri'], $tenant->id, $r['wheres']), [], idem())->status();
        if ($status !== 401) {
            $wrong[] = "{$r['key']} → {$status}";
        }
    }
    expect($wrong)->toBe([]);
});

it('never lets another restaurant\'s Owner into a restaurant', function () {
    [, $tenant] = member(Role::Owner);
    [$stranger] = member(Role::Owner);
    $this->actingAs($stranger);
    $wrong = [];
    foreach (matrixRoutes() as $r) {
        if (! str_starts_with($r['uri'], 'api/v1/t/{tenant}')) {
            continue;
        }
        $status = $this->json($r['method'], matrixUrl($r['uri'], $tenant->id, $r['wheres']), [], idem())->status();
        if (! in_array($status, [403, 404], true)) {
            $wrong[] = "{$r['key']} → {$status}";
        }
    }
    expect($wrong)->toBe([]);
});

it('keeps restaurant users out of the platform', function () {
    [$owner] = member(Role::Owner);
    $this->actingAs($owner);
    $wrong = [];
    foreach (matrixRoutes() as $r) {
        if (! str_starts_with($r['uri'], 'api/v1/platform')) {
            continue;
        }
        $status = $this->json($r['method'], matrixUrl($r['uri'], 1, $r['wheres']), [], idem())->status();
        // 404 (the platform doesn't exist for them), or 429 from a route limit that runs first.
        if (! in_array($status, [404, 429], true)) {
            $wrong[] = "{$r['key']} → {$status}";
        }
    }
    expect($wrong)->toBe([]);
});

it('lets Staff change nothing but the sold-out switches', function () {
    [, $tenant] = member(Role::Owner);
    [$staff] = member(Role::Staff, $tenant);
    $this->actingAs($staff);
    $wrong = [];
    foreach (matrixRoutes() as $r) {
        if (! str_starts_with($r['uri'], 'api/v1/t/{tenant}') || $r['method'] === 'GET' || in_array($r['key'], STAFF_WRITES, true)) {
            continue;
        }
        // These decide the permission from the request (which role to invite, which bulk action).
        $body = ['POST api/v1/t/{tenant}/invitations' => ['email' => 'new@example.com', 'role' => 'staff'], 'POST api/v1/t/{tenant}/items/bulk' => ['ids' => [1], 'action' => 'delete']][$r['key']] ?? [];
        $status = $this->json($r['method'], matrixUrl($r['uri'], $tenant->id, $r['wheres']), $body, idem())->status();
        // 403 from the Gate, or 404 when the (made-up) record is looked up first; never accepted or validated.
        if (! in_array($status, [403, 404], true)) {
            $wrong[] = "{$r['key']} → {$status}";
        }
    }
    expect($wrong)->toBe([]);

    // L13: reading the menu (needed for the sold-out switches) is intended for Staff.
    foreach (['items', 'menus', 'tags', 'addon-groups'] as $read) {
        $this->getJson(matrixUrl("api/v1/t/{tenant}/{$read}", $tenant->id))->assertOk();
    }
    $this->getJson(matrixUrl('api/v1/t/{tenant}/members', $tenant->id))->assertForbidden();
});

it('keeps billing and the account address for the Owner', function () {
    [, $tenant] = member(Role::Owner);
    [$manager] = member(Role::Manager, $tenant);
    $this->actingAs($manager);
    foreach (OWNER_ONLY as $key) {
        [$method, $uri] = explode(' ', $key, 2);
        expect($this->json($method, matrixUrl($uri, $tenant->id), [], idem())->status())->toBeIn([403, 404], $key);
    }
    expect($this->getJson(matrixUrl('api/v1/t/{tenant}/billing', $tenant->id))->status())->toBe(403);
});
