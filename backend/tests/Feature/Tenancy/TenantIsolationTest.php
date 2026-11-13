<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Impersonation;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantSlugHistory;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\MissingTenantException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

beforeEach(fn () => Notification::fake());

it('returns 404 for every restaurant route of a restaurant you do not belong to', function (string $method, string $path) {
    [$intruder] = member(Role::Owner);
    $victim = Tenant::factory()->create();

    $this->actingAs($intruder)
        ->json($method, "/api/v1/t/{$victim->id}{$path}", ['email' => 'x@example.com', 'role' => 'staff', 'slug' => 'stolen-slug'], idem())
        ->assertNotFound();
})->with([
    ['GET', ''],
    ['GET', '/members'],
    ['GET', '/invitations'],
    ['POST', '/invitations'],
    ['PATCH', '/account/slug'],
    ['GET', '/activity'],
]);

it('cannot reach another restaurant\'s members or invitations through its own restaurant', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $foreignMember] = member(Role::Staff);

    $foreignInvitation = app(CurrentTenant::class)->runAs(Tenant::findOrFail($foreignMember->tenant_id), fn () => Invitation::create([
        'email' => 'x@example.com', 'role' => Role::Staff, 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addDay(),
    ]));

    $this->actingAs($owner)->patchJson("/api/v1/t/{$tenant->id}/members/{$foreignMember->id}", ['role' => 'manager'], idem())->assertNotFound();
    $this->actingAs($owner)->deleteJson("/api/v1/t/{$tenant->id}/members/{$foreignMember->id}", [], idem())->assertNotFound();
    $this->actingAs($owner)->deleteJson("/api/v1/t/{$tenant->id}/invitations/{$foreignInvitation->id}", [], idem())->assertNotFound();

    expect($foreignMember->fresh()->role)->toBe(Role::Staff)
        ->and(Invitation::withoutTenancy()->find($foreignInvitation->id))->not->toBeNull();
});

it('lists only the current restaurant\'s members', function () {
    [$owner, $tenant] = member(Role::Owner);
    member(Role::Staff, $tenant);
    member(Role::Manager); // another restaurant

    $this->actingAs($owner)->getJson("/api/v1/t/{$tenant->id}/members")->assertOk()->assertJsonCount(2, 'data');
});

it('fails closed when tenant-owned data is read without a current tenant', function () {
    Invitation::query()->get();
})->throws(MissingTenantException::class);

it('fills in tenant_id and refuses rows for another tenant', function () {
    [$a, $b] = Tenant::factory()->count(2)->create();
    $current = app(CurrentTenant::class);

    $invitation = $current->runAs($a, fn () => Invitation::create([
        'email' => 'x@example.com', 'role' => Role::Staff, 'token_hash' => str_repeat('b', 64), 'expires_at' => now()->addDay(),
    ]));

    expect($invitation->tenant_id)->toBe($a->id);

    $current->runAs($a, fn () => (new Invitation)->forceFill([
        'tenant_id' => $b->id, 'email' => 'y@example.com', 'role' => Role::Staff, 'token_hash' => str_repeat('c', 64), 'expires_at' => now()->addDay(),
    ])->save());
})->throws(MissingTenantException::class);

it('shows each restaurant only its own rows', function () {
    [$a, $b] = Tenant::factory()->count(2)->create();
    $current = app(CurrentTenant::class);
    foreach ([$a, $b] as $i => $tenant) {
        $current->runAs($tenant, fn () => Invitation::create([
            'email' => "x{$i}@example.com", 'role' => Role::Staff, 'token_hash' => str_repeat((string) $i, 64), 'expires_at' => now()->addDay(),
        ]));
    }

    expect($current->runAs($a, fn () => Invitation::pluck('email')->all()))->toBe(['x0@example.com']);
});

it('makes every model with a tenant_id column use BelongsToTenant (except the documented bridges)', function () {
    // Bridges, plus platform records only the Super Admin area reads (payments, "log in as").
    $bridges = [Membership::class, ActivityLog::class, TenantSlugHistory::class, Payment::class, Impersonation::class];

    $offenders = collect(File::files(app_path('Models')))
        ->map(fn ($file) => 'App\\Models\\'.$file->getFilenameWithoutExtension())
        ->filter(fn ($class) => is_subclass_of($class, Model::class))
        ->reject(fn ($class) => in_array($class, $bridges, true) || $class === User::class || $class === Tenant::class)
        ->filter(fn ($class) => Schema::hasColumn((new $class)->getTable(), 'tenant_id'))
        ->reject(fn ($class) => in_array(BelongsToTenant::class, class_uses_recursive($class), true))
        ->values();

    expect($offenders->all())->toBe([]);
});
