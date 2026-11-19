<?php

use App\Enums\Role;
use App\Models\MenuRequest;
use App\Notifications\MenuReadyNotification;
use App\Notifications\MenuRequestNotification;
use App\Support\PlatformSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
 * "Add my menu for me" (owner, 2026-11-19, QUESTIONS #235): photos of the paper
 * menu, kept privately; the Super Admins are told, see them, mark the work and
 * the owner hears when it is done.
 */
beforeEach(fn () => Storage::fake('local'));

function jpeg(string $name = 'menu.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 1200, 1600);
}

it('takes photos and a PDF of the paper menu, keeps them private and tells every Super Admin', function () {
    Notification::fake();
    [$owner, $tenant] = member(Role::Owner);
    $admin = superAdmin();
    $pdf = UploadedFile::fake()->createWithContent('menu.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

    $id = $this->actingAs($owner)->post(t($tenant, 'menu-requests'), ['files' => [jpeg('page 1.jpg'), jpeg('page 2.jpg'), $pdf], 'note' => 'Prices on page 2 changed.'], idem())
        ->assertCreated()->assertJsonPath('data.files', 3)->json('data.id');

    $r = MenuRequest::findOrFail($id);
    expect($r->status)->toBe('new')->and($r->note)->toBe('Prices on page 2 changed.')
        ->and(array_column($r->files, 'mime'))->toBe(['image/jpeg', 'image/jpeg', 'application/pdf'])
        ->and(array_column($r->files, 'name'))->toBe(['page 1.jpg', 'page 2.jpg', 'menu.pdf']);
    foreach ($r->files as $f) {
        Storage::disk('local')->assertExists($r->dir().'/'.$f['file']);
        expect($f['file'])->toMatch('/^\d{2}-[A-Za-z0-9]{12}\.(jpg|pdf)$/');       // never the uploaded name
    }
    Notification::assertSentTo($admin, MenuRequestNotification::class, fn ($n) => $n->files === 3 && $n->restaurant === $tenant->name);
    $this->actingAs($owner)->getJson(t($tenant, 'menu-requests'))->assertOk()
        ->assertJsonPath('data.enabled', true)->assertJsonPath('data.requests.0.status', 'new');
});

it('refuses files that are not pictures or PDFs, whatever their name, too many, and too often', function () {
    [$owner, $tenant] = member(Role::Owner);
    $fake = UploadedFile::fake()->createWithContent('menu.jpg', '<?php echo "hi";');
    $this->actingAs($owner)->postJson(t($tenant, 'menu-requests'), ['files' => [$fake]], idem())->assertInvalid('files.0');
    $this->actingAs($owner)->postJson(t($tenant, 'menu-requests'), ['files' => array_fill(0, 11, jpeg())], idem())->assertInvalid('files');
    $this->actingAs($owner)->postJson(t($tenant, 'menu-requests'), ['files' => []], idem())->assertInvalid('files');
    expect(MenuRequest::count())->toBe(0);

    $this->travel(61)->minutes();   // 6 sends an hour (throttle) are used up above
    foreach (range(1, MenuRequest::MAX_OPEN) as $i) {
        $this->actingAs($owner)->postJson(t($tenant, 'menu-requests'), ['files' => [jpeg()]], idem())->assertCreated();
    }
    $this->actingAs($owner)->postJson(t($tenant, 'menu-requests'), ['files' => [jpeg()]], idem())->assertInvalid('files');
    foreach (range(1, 3) as $i) {
        $this->actingAs($owner)->postJson(t($tenant, 'menu-requests'), ['files' => [jpeg()]], idem());
    }
    $this->actingAs($owner)->postJson(t($tenant, 'menu-requests'), ['files' => [jpeg()]], idem())->assertStatus(429);
});

it('is for owners and managers of that restaurant only, and can be switched off', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$staff] = member(Role::Staff, $tenant);
    [$other] = member(Role::Owner);

    $this->actingAs($staff)->postJson(t($tenant, 'menu-requests'), ['files' => [jpeg()]], idem())->assertForbidden();
    $this->actingAs($other)->postJson(t($tenant, 'menu-requests'), ['files' => [jpeg()]], idem())->assertNotFound();

    PlatformSettings::save(['menu_help_enabled' => false]);
    $this->actingAs($owner)->getJson(t($tenant, 'menu-requests'))->assertJsonPath('data.enabled', false);
    $this->actingAs($owner)->postJson(t($tenant, 'menu-requests'), ['files' => [jpeg()]], idem())->assertNotFound();
});

it('lets the Super Admin see the photos, mark the work and tell the owner when it is done', function () {
    Notification::fake();
    [$owner, $tenant] = member(Role::Owner);
    $admin = superAdmin();
    $id = $this->actingAs($owner)->postJson(t($tenant, 'menu-requests'), ['files' => [jpeg()]], idem())->json('data.id');

    $this->actingAs($admin)->getJson(p('menu-requests'))->assertOk()
        ->assertJsonPath('data.open', 1)->assertJsonPath('data.requests.0.restaurant.name', $tenant->name)
        ->assertJsonPath('data.requests.0.user.email', $owner->email)->assertJsonPath('data.requests.0.files.0.mime', 'image/jpeg');
    $this->actingAs($admin)->get(p("menu-requests/{$id}/files/0"))->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs($admin)->get(p("menu-requests/{$id}/files/5"))->assertNotFound();

    $this->actingAs($admin)->putJson(p("menu-requests/{$id}"), ['status' => 'working'], idem())->assertOk();
    Notification::assertNotSentTo($owner, MenuReadyNotification::class);
    $this->actingAs($admin)->putJson(p("menu-requests/{$id}"), ['status' => 'done'], idem())->assertOk()->assertJsonPath('data.status', 'done');
    Notification::assertSentToTimes($owner, MenuReadyNotification::class, 1);
    $this->actingAs($admin)->putJson(p("menu-requests/{$id}"), ['status' => 'done'], idem())->assertOk();
    Notification::assertSentToTimes($owner, MenuReadyNotification::class, 1);   // once

    // Nobody else sees them.
    $this->actingAs($owner)->getJson(p('menu-requests'))->assertNotFound();
    $this->actingAs($owner)->get(p("menu-requests/{$id}/files/0"))->assertNotFound();

    $dir = MenuRequest::findOrFail($id)->dir();
    $this->actingAs($admin)->deleteJson(p("menu-requests/{$id}"), [], idem())->assertNoContent();
    Storage::disk('local')->assertMissing($dir);
});

it('deletes done requests and their photos after 30 days, keeps open ones', function () {
    [$owner, $tenant] = member(Role::Owner);
    $done = $this->actingAs($owner)->postJson(t($tenant, 'menu-requests'), ['files' => [jpeg()]], idem())->json('data.id');
    $open = $this->actingAs($owner)->postJson(t($tenant, 'menu-requests'), ['files' => [jpeg()]], idem())->json('data.id');
    MenuRequest::whereKey($done)->update(['status' => 'done']);
    $dir = MenuRequest::findOrFail($done)->dir();

    $this->travel(31)->days();
    $this->artisan('schedule:test', ['--name' => 'menu-requests:prune']);
    expect(MenuRequest::find($done))->toBeNull()->and(MenuRequest::find($open))->not->toBeNull();
    Storage::disk('local')->assertMissing($dir);
});

it('removes the photos when the restaurant is deleted for good', function () {
    [$owner, $tenant] = member(Role::Owner);
    $id = $this->actingAs($owner)->postJson(t($tenant, 'menu-requests'), ['files' => [jpeg()]], idem())->json('data.id');
    $dir = MenuRequest::findOrFail($id)->dir();
    $tenant->forceDelete();
    Storage::disk('local')->assertMissing($dir);
    expect(MenuRequest::find($id))->toBeNull();
});
