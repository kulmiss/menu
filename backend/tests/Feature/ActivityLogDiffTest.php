<?php

use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\ActivityLogger;

/*
 * Found by the My order switch tests (QUESTIONS #236): the log's "from" showed the new
 * value, because diff() runs after save(), when getOriginal() has caught up already.
 */

it('logs the old value as "from" and the new one as "to" after a save', function () {
    $tenant = Tenant::factory()->create(['name' => 'Old name']);
    $tenant->forceFill(['name' => 'New name'])->save();
    expect(ActivityLogger::diff($tenant))->toBe(['name' => ['from' => 'Old name', 'to' => 'New name']]);
});

it('reads the old value through the casts, like before', function () {
    $tenant = Tenant::factory()->create();
    asTenant($tenant, function () {
        $a = TenantAppearance::create([...TenantAppearance::DEFAULTS, 'show_prices' => true]);
        $a = TenantAppearance::query()->firstOrFail();
        $a->fill(['show_prices' => false])->save();
        expect(ActivityLogger::diff($a)['show_prices'])->toBe(['from' => true, 'to' => false]);
    });
});

it('logs nothing when nothing changed', function () {
    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['name' => $tenant->name])->save();
    expect(ActivityLogger::diff($tenant))->toBe([]);
});
