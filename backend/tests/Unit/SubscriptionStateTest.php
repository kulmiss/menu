<?php

use App\Enums\SubscriptionState;
use App\Models\Tenant;
use Tests\TestCase;

uses(TestCase::class);

function tenantWith(array $attributes): Tenant
{
    return (new Tenant)->forceFill(array_merge(['status' => Tenant::STATUS_ACTIVE], $attributes));
}

it('follows the expiry timeline: grace 7 days, read-only 14 more, then unavailable', function (int $daysSinceExpiry, SubscriptionState $expected) {
    $tenant = tenantWith(['paid_until' => now()->subDays($daysSinceExpiry)->subMinute()]);

    expect($tenant->subscriptionState())->toBe($expected);
})->with([
    'expired today' => [0, SubscriptionState::Grace],
    'day 6' => [6, SubscriptionState::Grace],
    'day 7' => [7, SubscriptionState::ReadOnly],
    'day 20' => [20, SubscriptionState::ReadOnly],
    'day 21' => [21, SubscriptionState::Unavailable],
]);

it('is in trial before the trial ends and active while paid', function () {
    expect(tenantWith(['trial_ends_at' => now()->addDay()])->subscriptionState())->toBe(SubscriptionState::Trial)
        ->and(tenantWith(['trial_ends_at' => now()->subDays(30), 'paid_until' => now()->addMonth()])->subscriptionState())->toBe(SubscriptionState::Active);
});

it('counts the timeline from the later of trial end and paid-until', function () {
    $tenant = tenantWith(['trial_ends_at' => now()->subDays(40), 'paid_until' => now()->subDays(2)]);

    expect($tenant->subscriptionState())->toBe(SubscriptionState::Grace);
});

it('lets suspension win over everything', function () {
    $tenant = tenantWith(['status' => Tenant::STATUS_SUSPENDED, 'paid_until' => now()->addYear()]);

    expect($tenant->subscriptionState())->toBe(SubscriptionState::Suspended)
        ->and($tenant->subscriptionState()->showsPublicMenu())->toBeFalse();
});

it('only allows writes before read-only, and shows the menu until unavailable', function () {
    expect(SubscriptionState::Grace->allowsWrites())->toBeTrue()
        ->and(SubscriptionState::ReadOnly->allowsWrites())->toBeFalse()
        ->and(SubscriptionState::ReadOnly->showsPublicMenu())->toBeTrue()
        ->and(SubscriptionState::Unavailable->showsPublicMenu())->toBeFalse();
});
