<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Item;
use App\Models\Membership;
use App\Models\Menu;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Headers for a create/change request: a fresh Idempotency-Key. */
function idem(?string $key = null): array
{
    return ['Idempotency-Key' => $key ?? (string) Str::uuid(), 'Accept' => 'application/json'];
}

/**
 * A user with the given role in a (new or given) restaurant.
 *
 * @return array{0: User, 1: Tenant, 2: Membership}
 */
function member(Role $role = Role::Owner, ?Tenant $tenant = null, bool $canEditPrices = false): array
{
    $tenant ??= Tenant::factory()->create();
    $user = User::factory()->create();
    $membership = Membership::factory()->for($tenant)->for($user)->create([
        'role' => $role,
        'can_edit_prices' => $canEditPrices,
    ]);

    // Reload, as a real request would: a just-created model lacks columns with DB defaults.
    return [$user->fresh(), $tenant->fresh(), $membership];
}

/**
 * Runs code as the given restaurant (for setting up tenant-owned rows).
 *
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function asTenant(Tenant $tenant, callable $callback): mixed
{
    return app(CurrentTenant::class)->runAs($tenant, $callback);
}

/**
 * A menu with one category and one item (price 120.00) in the restaurant.
 *
 * @return array{0: Menu, 1: Category, 2: Item}
 */
function sampleMenu(Tenant $tenant): array
{
    return asTenant($tenant, function () {
        $menu = Menu::create(['name' => ['en' => 'All day'], 'slug' => 'all-day-'.Str::random(4)]);
        $category = Category::create(['menu_id' => $menu->id, 'name' => ['en' => 'Coffee']]);
        $item = Item::create(['category_id' => $category->id, 'name' => ['en' => 'Macchiato'], 'price_minor' => 12000]);

        return [$menu, $category, $item];
    });
}

/** URL of a restaurant API route. */
function t(Tenant|int $tenant, string $path = ''): string
{
    $id = $tenant instanceof Tenant ? $tenant->id : $tenant;

    return "/api/v1/restaurant/{$id}".($path === '' ? '' : '/'.ltrim($path, '/'));
}

// Platform (Super Admin) helpers.

function superAdmin(bool $twoFactor = true): User
{
    return User::factory()->create([
        'is_super_admin' => true,
        'two_factor_secret' => $twoFactor ? encrypt('secret') : null,
        'two_factor_confirmed_at' => $twoFactor ? now() : null,
    ])->fresh();
}

/** Requests as the browser app sends them (cookie session, like the SPA). */
function browser(): void
{
    test()->withHeaders(['Origin' => config('app.url'), 'Referer' => config('app.url').'/platform']);
}

function p(string $path = ''): string
{
    return '/api/v1/super/'.ltrim($path, '/');
}

/** Mobile app sign-in fields (QUESTIONS #187). */
function device(array $extra = []): array
{
    return ['device_name' => 'Pixel 8', 'platform' => 'android', 'app_version' => '1.0.0+1', ...$extra];
}

/** Headers of an app request with this token. */
function bearer(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
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

/** A stand-in for the built admin app (frontend/ → public/app). */
function withBuiltSpa(callable $test): void
{
    $index = public_path('app/index.html');
    $existed = is_file($index);
    $original = $existed ? File::get($index) : null;
    File::ensureDirectoryExists(dirname($index));
    File::put($index, '<!doctype html><html><head><title>Menu</title></head><body><div id="root"></div></body></html>');
    try {
        $test();
    } finally {
        $existed ? File::put($index, $original) : File::delete($index);
    }
}
