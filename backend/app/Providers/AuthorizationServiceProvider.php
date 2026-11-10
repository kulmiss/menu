<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Restaurant permissions (PLAN.md §5), checked on the server for every
 * request. Each ability reads the user's membership in the current restaurant.
 */
class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $role = fn (): ?Role => app(CurrentTenant::class)->membership()?->role;

        $managerOrOwner = fn (User $user): bool => in_array($role(), [Role::Owner, Role::Manager], true);

        Gate::define('team.view', $managerOrOwner);
        Gate::define('activity.view', $managerOrOwner);
        Gate::define('menu.manage', $managerOrOwner);

        Gate::define('team.manage', fn (User $user, Role $target): bool => $role()?->canManage($target) ?? false);

        Gate::define('account.manage', fn (User $user): bool => $role() === Role::Owner);

        Gate::define('menu.toggle_sold_out', fn (User $user): bool => $role() !== null);

        Gate::define('menu.edit_prices', fn (User $user): bool => app(CurrentTenant::class)->membership()?->canEditPrices() ?? false);
    }
}
