<?php

use App\Enums\Role;
use App\Models\Media;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

function upload(Tenant $tenant, UploadedFile $file)
{
    return test()->post(t($tenant, 'media'), ['file' => $file], idem());
}

it('stores a photo in every size the menu needs', function () {
    [$owner, $tenant] = member(Role::Owner);

    $res = $this->actingAs($owner)->post(t($tenant, 'media'), ['file' => UploadedFile::fake()->image('dish.jpg', 1600, 1200)], idem())
        ->assertCreated()
        ->assertJsonPath('data.width', 1600)
        ->assertJsonPath('data.height', 1200);

    $media = asTenant($tenant, fn () => Media::sole());
    foreach (['320.webp', '640.webp', '800.webp', '1280.webp', '640.jpg', 'og.jpg'] as $file) {
        Storage::disk('public')->assertExists("{$media->base_path}/{$file}");
    }
    [$w, $h] = getimagesizefromstring(Storage::disk('public')->get("{$media->base_path}/og.jpg"));
    expect([$w, $h])->toBe([1200, 630])
        ->and($res->json('data.dominant_color'))->toMatch('/^#[0-9a-f]{6}$/')
        ->and($media->base_path)->toStartWith($tenant->id.'/')
        ->and($media->placeholder)->toStartWith('data:image/webp;base64,')
        ->and(strlen((string) $media->placeholder))->toBeLessThan(300);

    // AVIF only where GD can write it; WebP always.
    $avif = ImageProcessor::supportsAvif();
    expect(array_key_exists('avif640', $media->variants))->toBe($avif);
});

it('never enlarges small photos', function () {
    [$owner, $tenant] = member(Role::Owner);

    $this->actingAs($owner)->post(t($tenant, 'media'), ['file' => UploadedFile::fake()->image('small.png', 300, 200)], idem())->assertCreated();

    $media = asTenant($tenant, fn () => Media::sole());
    [$w] = getimagesizefromstring(Storage::disk('public')->get("{$media->base_path}/1280.webp"));
    expect($w)->toBe(300);
});

it('explains why HEIC and other files are refused', function () {
    [$owner, $tenant] = member(Role::Owner);

    $this->actingAs($owner)->post(t($tenant, 'media'), ['file' => UploadedFile::fake()->create('photo.heic', 100, 'image/heic')], idem())
        ->assertUnprocessable()->assertJsonValidationErrors(['file' => 'HEIC']);
    $this->post(t($tenant, 'media'), ['file' => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf')], idem())
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->post(t($tenant, 'media'), ['file' => UploadedFile::fake()->image('big.jpg')->size(9000)], idem())
        ->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('lets only owners and managers upload, within the plan', function () {
    [$staff, $tenant] = member(Role::Staff);
    $this->actingAs($staff)->post(t($tenant, 'media'), ['file' => UploadedFile::fake()->image('a.jpg')], idem())->assertForbidden();

    [$owner, $limited] = member(Role::Owner);
    $limited->forceFill(['plan_id' => Plan::create(['name' => ['en' => 'S'], 'slug' => 's', 'limits' => ['photos' => 1]])->id])->save();
    $this->actingAs($owner)->post(t($limited, 'media'), ['file' => UploadedFile::fake()->image('a.jpg')], idem())->assertCreated();
    $this->post(t($limited, 'media'), ['file' => UploadedFile::fake()->image('b.jpg')], idem())
        ->assertStatus(402)->assertJsonPath('code', 'limits.photos');
});

it('puts photos on items and deletes a replaced one', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);

    $first = $this->actingAs($owner)->post(t($tenant, 'media'), ['file' => UploadedFile::fake()->image('a.jpg')], idem())->json('data.id');
    $second = $this->post(t($tenant, 'media'), ['file' => UploadedFile::fake()->image('b.jpg')], idem())->json('data.id');
    $oldPath = asTenant($tenant, fn () => Media::find($first)->base_path);

    $this->patchJson(t($tenant, "items/{$item->id}"), ['media_id' => $first], idem())
        ->assertOk()->assertJsonPath('data.media.id', $first);
    $this->patchJson(t($tenant, "items/{$item->id}"), ['media_id' => $second], idem())->assertOk();

    expect(Media::withoutTenancy()->find($first))->toBeNull();
    Storage::disk('public')->assertMissing("{$oldPath}/640.jpg");

    $this->deleteJson(t($tenant, "items/{$item->id}"), [], idem())->assertNoContent();
    expect(Media::withoutTenancy()->count())->toBe(0);
});

it('refuses another restaurant\'s photo', function () {
    [$owner, $mine] = member(Role::Owner);
    [, , $item] = sampleMenu($mine);
    [$other, $theirs] = member(Role::Owner);
    $foreign = $this->actingAs($other)->post(t($theirs, 'media'), ['file' => UploadedFile::fake()->image('a.jpg')], idem())->json('data.id');

    $this->actingAs($owner)->patchJson(t($mine, "items/{$item->id}"), ['media_id' => $foreign], idem())
        ->assertJsonValidationErrors('media_id');
    $this->deleteJson(t($mine, "media/{$foreign}"), [], idem())->assertNotFound();
});

it('saves banners a little lighter (they are the largest paint of the menu)', function () {
    [$owner, $tenant] = member(Role::Owner);
    // A detailed photo, so the quality setting makes a measurable difference.
    $img = imagecreatetruecolor(1600, 800);
    mt_srand(7);
    for ($i = 0; $i < 20000; $i++) {
        imagefilledellipse($img, mt_rand(0, 1600), mt_rand(0, 800), mt_rand(4, 40), mt_rand(4, 40), imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
    }
    $path = tempnam(sys_get_temp_dir(), 'ban').'.jpg';
    imagejpeg($img, $path, 92);

    foreach ([null, 'banner'] as $purpose) {
        $this->actingAs($owner)->post(t($tenant, 'media'), array_filter([
            'file' => new UploadedFile($path, 'banner.jpg', 'image/jpeg', null, true), 'purpose' => $purpose,
        ]), idem())->assertCreated();
    }
    [$dish, $banner] = asTenant($tenant, fn () => Media::orderBy('id')->get()->all());
    $size = fn (Media $m) => strlen((string) Storage::disk('public')->get("{$m->base_path}/800.webp"));

    expect($size($banner))->toBeLessThan($size($dish));
    $this->actingAs($owner)->post(t($tenant, 'media'), ['file' => new UploadedFile($path, 'b.jpg', 'image/jpeg', null, true), 'purpose' => 'poster'], idem())
        ->assertInvalid('purpose');
});
