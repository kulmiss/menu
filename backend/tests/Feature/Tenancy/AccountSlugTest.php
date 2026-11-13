<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\TenantSlugHistory;

it('lets the owner change the slug and keeps the old one reserved', function () {
    [$owner, $tenant] = member(Role::Owner);
    $tenant->update(['slug' => 'old-name']);

    $this->actingAs($owner)
        ->patchJson("/api/v1/t/{$tenant->id}/account/slug", ['slug' => 'new-name'], idem())
        ->assertOk()
        ->assertJsonPath('data.slug', 'new-name');

    expect(TenantSlugHistory::where('tenant_id', $tenant->id)->pluck('old_slug')->all())->toBe(['old-name'])
        ->and($tenant->fresh()->cache_version)->toBe(2);

    // Nobody else can take the old slug.
    [$other, $otherTenant] = member(Role::Owner);
    $this->actingAs($other)
        ->patchJson("/api/v1/t/{$otherTenant->id}/account/slug", ['slug' => 'old-name'], idem())
        ->assertJsonValidationErrors('slug');
});

it('lets a restaurant switch back to its own old slug', function () {
    [$owner, $tenant] = member(Role::Owner);
    $tenant->update(['slug' => 'first']);

    $this->actingAs($owner)->patchJson("/api/v1/t/{$tenant->id}/account/slug", ['slug' => 'second'], idem())->assertOk();
    $this->actingAs($owner)->patchJson("/api/v1/t/{$tenant->id}/account/slug", ['slug' => 'first'], idem())->assertOk();

    expect(Tenant::find($tenant->id)->slug)->toBe('first')
        ->and(TenantSlugHistory::where('tenant_id', $tenant->id)->pluck('old_slug')->all())->toBe(['second']);
});

it('does not let managers or staff change the slug', function (Role $role) {
    [$user, $tenant] = member($role);

    $this->actingAs($user)->patchJson("/api/v1/t/{$tenant->id}/account/slug", ['slug' => 'whatever'], idem())->assertForbidden();
})->with([Role::Manager, Role::Staff]);
