<?php

use App\Enums\Role;
use App\Models\Item;
use App\Models\Membership;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\Tag;
use App\Models\Tenant;
use App\Support\Import\MenuSheet;
use Illuminate\Http\UploadedFile;

function csvFile(string $body, string $name = 'menu.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $body);
}

function preview(Tenant $tenant, UploadedFile $file)
{
    return test()->post(t($tenant, 'import/preview'), ['file' => $file], idem());
}

const HEADER = "menu,category,item,price,sizes,summary,tags,allergens,visible,sold_out,calories\n";

it('previews new and changed items without saving anything', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant); // menu "All day …", category Coffee, Macchiato at 120.00
    $menuName = asTenant($tenant, fn () => Menu::first()->name['en']);

    $res = $this->actingAs($owner)->post(t($tenant, 'import/preview'), ['file' => csvFile(HEADER
        ."{$menuName},Coffee,Macchiato,130,,,,,,,\n"
        ."{$menuName},Coffee,Latte,,Small=60; Large=80,Milky,vegan,milk,yes,no,180\n"
        ."Drinks,Juice,Mango,90,,,,,,,\n")], idem())->assertOk();

    $res->assertJsonPath('data.summary.create', 2)
        ->assertJsonPath('data.summary.update', 1)
        ->assertJsonPath('data.summary.errors', 0)
        ->assertJsonPath('data.summary.new_menus', 1)
        ->assertJsonPath('data.rows.0.action', 'update')
        ->assertJsonPath('data.rows.0.changes', ['price']);

    expect(asTenant($tenant, fn () => Item::count()))->toBe(1);
});

it('applies a preview: creates menus, categories, sizes, tags, and updates prices', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $macchiato] = sampleMenu($tenant);
    $menuName = asTenant($tenant, fn () => Menu::first()->name['en']);

    $token = $this->actingAs($owner)->post(t($tenant, 'import/preview'), ['file' => csvFile(HEADER
        ."{$menuName},Coffee,macchiato,130,,,,,,,\n"
        ."{$menuName},Coffee,Latte,,Small=60; Large=80,Milky,\"vegan, House pick\",milk,yes,no,180\n"
        ."Drinks,Juice,Mango,\"Br 90,50\",,,,,no,,\n")], idem())->json('data.token');

    $this->postJson(t($tenant, 'import/commit'), ['token' => $token], idem())
        ->assertOk()->assertJsonPath('data.summary.create', 2);

    asTenant($tenant, function () use ($macchiato) {
        expect($macchiato->fresh()->price_minor)->toBe(13000);
        $latte = Item::where('name->en', 'Latte')->with('variants', 'tags')->sole();
        expect($latte->price_minor)->toBeNull()
            ->and($latte->variants->pluck('price_minor')->all())->toBe([6000, 8000])
            ->and($latte->tags->pluck('key')->filter()->values()->all())->toBe(['vegan'])
            ->and(Tag::whereNull('key')->sole()->name)->toBe(['en' => 'House pick'])
            ->and($latte->allergens())->toBe(['milk'])
            ->and($latte->calories)->toBe(180);
        $mango = Item::where('name->en', 'Mango')->sole();
        expect($mango->price_minor)->toBe(9050)->and($mango->is_visible)->toBeFalse()
            ->and(Menu::count())->toBe(2);
    });

    // The preview can't be used twice.
    $this->postJson(t($tenant, 'import/commit'), ['token' => $token], idem())->assertJsonValidationErrors('token');
});

it('reports row errors and refuses to import them', function () {
    [$owner, $tenant] = member(Role::Owner);

    $res = $this->actingAs($owner)->post(t($tenant, 'import/preview'), ['file' => csvFile(HEADER
        ."Food,Mains,Tibs,abc,,,,pollen,maybe,,\n"
        .",Mains,Shiro,80,,,,,,,\n"
        ."Food,Mains,Tibs,100,,,,,,,\n")], idem())->assertOk();

    $res->assertJsonPath('data.summary.errors', 3)
        ->assertJsonPath('data.rows.0.line', 2);
    expect($res->json('data.rows.0.errors'))->toHaveCount(3)
        ->and($res->json('data.rows.2.errors.0'))->toContain('line 2');

    $this->postJson(t($tenant, 'import/commit'), ['token' => $res->json('data.token')], idem())
        ->assertJsonValidationErrors('token');
    expect(asTenant($tenant, fn () => Item::count()))->toBe(0);
});

it('reads Excel files and semicolon CSVs', function () {
    [$owner, $tenant] = member(Role::Owner);
    $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    app(MenuSheet::class)->writeXlsx($path, [['menu' => 'Food', 'category' => 'Mains', 'item' => 'ቲብስ', 'price' => '250']]);

    $this->actingAs($owner)->post(t($tenant, 'import/preview'), ['file' => new UploadedFile($path, 'menu.xlsx', null, null, true)], idem())
        ->assertOk()->assertJsonPath('data.rows.0.item', 'ቲብስ')->assertJsonPath('data.summary.create', 1);

    $this->post(t($tenant, 'import/preview'), ['file' => csvFile("\xEF\xBB\xBFmenu;category;item;price\nFood;Mains;Shiro;80,50\n")], idem())
        ->assertOk()->assertJsonPath('data.summary.create', 1)->assertJsonPath('data.rows.0.errors', []);
});

it('explains files it cannot use', function () {
    [$owner, $tenant] = member(Role::Owner);

    $this->actingAs($owner)->post(t($tenant, 'import/preview'), ['file' => csvFile("name,price\nTea,10\n")], idem())
        ->assertJsonValidationErrors(['file' => 'menu, category, item']);
    $this->post(t($tenant, 'import/preview'), ['file' => UploadedFile::fake()->create('menu.pdf', 10)], idem())
        ->assertJsonValidationErrors('file');
});

it('exports the menu in the import format, and the export imports back unchanged', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    asTenant($tenant, fn () => $item->variants()->create(['name' => ['en' => 'Large'], 'price_minor' => 15050]));

    $csv = $this->actingAs($owner)->get(t($tenant, 'export?format=csv'))->assertOk()->streamedContent();
    expect($csv)->toStartWith("\xEF\xBB\xBFmenu,category,item")->toContain('Large=150.50');

    $res = $this->post(t($tenant, 'import/preview'), ['file' => csvFile($csv)], idem())->assertOk();
    expect($res->json('data.summary'))->toMatchArray(['create' => 0, 'update' => 0, 'unchanged' => 1, 'errors' => 0]);

    $this->get(t($tenant, 'export'))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $this->get(t($tenant, 'import/template?format=csv'))->assertOk();
});

it('is for owners and managers, within the plan', function () {
    [$staff, $tenant] = member(Role::Staff);
    $this->actingAs($staff)->get(t($tenant, 'export'))->assertForbidden();
    $this->post(t($tenant, 'import/preview'), ['file' => csvFile(HEADER)], idem())->assertForbidden();

    [$owner, $limited] = member(Role::Owner);
    $limited->forceFill(['plan_id' => Plan::create(['name' => ['en' => 'S'], 'slug' => 's', 'limits' => ['items' => 1]])->id])->save();
    $token = $this->actingAs($owner)->post(t($limited, 'import/preview'), ['file' => csvFile(HEADER."A,B,One,1,,,,,,,\nA,B,Two,2,,,,,,,\n")], idem())->json('data.token');
    $this->postJson(t($limited, 'import/commit'), ['token' => $token], idem())->assertStatus(402);
});

it('keeps previews per restaurant', function () {
    [$owner, $mine] = member(Role::Owner);
    [, $theirs] = member(Role::Owner);
    Membership::create(['tenant_id' => $theirs->id, 'user_id' => $owner->id, 'role' => Role::Owner]);

    $token = $this->actingAs($owner)->post(t($mine, 'import/preview'), ['file' => csvFile(HEADER."A,B,One,1,,,,,,,\n")], idem())->json('data.token');

    $this->postJson(t($theirs, 'import/commit'), ['token' => $token], idem())->assertJsonValidationErrors('token');
});
