<?php

namespace App\Support\Import;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemAllergen;
use App\Models\ItemVariant;
use App\Models\Menu;
use App\Models\Tag;
use App\Support\Features;
use App\Support\SystemTags;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Turns spreadsheet rows into a plan (what will be created or changed, and
 * what is wrong) and applies a plan. Items are matched by menu, category
 * and item name in the restaurant's main language. A blank cell leaves the
 * current value as it is.
 */
class ImportPlanner
{
    private const TRUE = ['yes', 'y', 'true', '1', 'x'];

    private const FALSE = ['no', 'n', 'false', '0'];

    /** English names people type for the built-in tags. */
    private const TAG_ALIASES = ['gluten-free' => 'gluten_free', 'gluten free' => 'gluten_free', 'veg' => 'vegetarian'];

    private const ALLERGEN_ALIASES = ['tree nuts' => 'nuts', 'sulfites' => 'sulphites', 'soya' => 'soy', 'egg' => 'eggs', 'peanut' => 'peanuts', 'shellfish' => 'crustaceans'];

    private string $locale;

    /** @var Collection<int, Menu> */
    private Collection $menus;

    /** @var Collection<int, Tag> */
    private Collection $tags;

    public function __construct(private readonly CurrentTenant $current) {}

    /**
     * @param  array<int, array<string, string>>  $sheetRows  line → cells
     * @return array{rows: list<array<string, mixed>>, summary: array<string, int>, normalized: list<array<string, mixed>>}
     */
    public function plan(array $sheetRows): array
    {
        $this->load();
        $rows = [];
        $normalized = [];
        $seen = [];
        $summary = ['create' => 0, 'update' => 0, 'unchanged' => 0, 'errors' => 0, 'new_menus' => 0, 'new_categories' => 0, 'new_tags' => 0];
        $newMenus = $newCategories = $newTags = [];

        foreach ($sheetRows as $line => $cells) {
            [$row, $errors] = $this->normalize($cells);
            $row['line'] = $line;
            $key = $this->key($row['menu'], $row['category'], $row['item']);

            if ($key !== '' && isset($seen[$key])) {
                $errors[] = __('menu.import.duplicate_row', ['line' => $seen[$key]]);
            }
            $seen[$key] = $line;

            $item = $errors === [] ? $this->findItem($row) : null;
            $changes = $errors === [] && $item ? $this->changes($item, $row) : [];
            $action = $errors !== [] ? 'error' : ($item === null ? 'create' : ($changes === [] ? 'unchanged' : 'update'));

            if ($errors === []) {
                if ($this->findMenu($row['menu']) === null) {
                    $newMenus[mb_strtolower($row['menu'])] = true;
                }
                if ($this->findCategory($row['menu'], $row['category']) === null) {
                    $newCategories[$this->key($row['menu'], $row['category'])] = true;
                }
                foreach ($row['tags'] ?? [] as $tag) {
                    if (! $this->resolveTag($tag) instanceof Tag) {
                        $newTags[mb_strtolower($tag)] = true;
                    }
                }
                $normalized[] = $row;
            }

            $summary[$action === 'error' ? 'errors' : $action]++;
            $rows[] = [
                'line' => $line,
                'action' => $action,
                'menu' => $row['menu'],
                'category' => $row['category'],
                'item' => $row['item'],
                'changes' => array_keys($changes),
                'errors' => $errors,
            ];
        }

        $summary['new_menus'] = count($newMenus);
        $summary['new_categories'] = count($newCategories);
        $summary['new_tags'] = count($newTags);

        return ['rows' => $rows, 'summary' => $summary, 'normalized' => $normalized];
    }

    /**
     * Applies normalized rows (from plan()). Callers run this in a transaction.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function apply(array $rows): void
    {
        $this->load();

        foreach ($rows as $row) {
            $menu = $this->findMenu($row['menu']) ?? $this->createMenu($row['menu']);
            $category = $this->findCategory($row['menu'], $row['category']) ?? $this->createCategory($menu, $row['category']);
            $item = $this->findItem($row);

            if ($item === null) {
                $item = Item::create([
                    'category_id' => $category->id,
                    'name' => [$this->locale => $row['item']],
                    'sort' => ((int) $category->items()->max('sort')) + 1,
                    'is_visible' => $row['visible'] ?? true,
                    'is_sold_out' => Features::here('sold_out') && ($row['sold_out'] ?? false),
                ]);
                $item->setRelation('variants', new Collection);
                $item->setRelation('tags', new Collection);
                $item->setRelation('allergenRows', new Collection);
                $category->items->push($item);
            }

            $this->write($item, $row);
        }
    }

    /**
     * @param  array<string, string>  $cells
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function normalize(array $cells): array
    {
        $errors = [];
        $row = [
            'menu' => $cells['menu'] ?? '',
            'category' => $cells['category'] ?? '',
            'item' => $cells['item'] ?? '',
        ];
        foreach (['menu' => 80, 'category' => 80, 'item' => 120] as $field => $max) {
            if ($row[$field] === '') {
                $errors[] = __('menu.import.required', ['column' => $field]);
            } elseif (mb_strlen($row[$field]) > $max) {
                $errors[] = __('menu.import.too_long', ['column' => $field, 'max' => $max]);
            }
        }

        if (($cells['price'] ?? '') !== '') {
            $row['price'] = $this->money($cells['price']);
            if ($row['price'] === null) {
                $errors[] = __('menu.import.bad_price', ['value' => $cells['price']]);
            }
        }

        if (($cells['sizes'] ?? '') !== '') {
            $sizes = [];
            foreach (preg_split('/[;|\n]/', $cells['sizes']) ?: [] as $part) {
                if (trim($part) === '') {
                    continue;
                }
                $at = max((int) strrpos($part, '='), (int) strrpos($part, ':'));
                $name = trim(substr($part, 0, $at));
                $price = $at > 0 ? $this->money(substr($part, $at + 1)) : null;
                if ($name === '' || $price === null) {
                    $errors[] = __('menu.import.bad_size', ['value' => trim($part)]);

                    continue;
                }
                $sizes[] = ['name' => $name, 'price' => $price];
            }
            $row['sizes'] = $sizes;
        }

        foreach (['summary' => 160, 'description' => 2000] as $field => $max) {
            if (($cells[$field] ?? '') !== '') {
                $row[$field] = $cells[$field];
                if (mb_strlen($cells[$field]) > $max) {
                    $errors[] = __('menu.import.too_long', ['column' => $field, 'max' => $max]);
                }
            }
        }

        if (($cells['tags'] ?? '') !== '') {
            $row['tags'] = array_values(array_unique(array_filter(array_map('trim', explode(',', $cells['tags'])))));
        }

        if (($cells['allergens'] ?? '') !== '') {
            $row['allergens'] = [];
            foreach (array_filter(array_map('trim', explode(',', $cells['allergens']))) as $name) {
                $key = $this->allergen($name);
                $key === null
                    ? $errors[] = __('menu.import.bad_allergen', ['value' => $name])
                    : $row['allergens'][] = $key;
            }
            $row['allergens'] = array_values(array_unique($row['allergens']));
        }

        foreach (['visible', 'sold_out'] as $field) {
            $value = mb_strtolower($cells[$field] ?? '');
            if ($value === '') {
                continue;
            }
            if (in_array($value, self::TRUE, true) || in_array($value, self::FALSE, true)) {
                $row[$field] = in_array($value, self::TRUE, true);
            } else {
                $errors[] = __('menu.import.bad_yes_no', ['column' => $field, 'value' => $cells[$field]]);
            }
        }

        foreach (['calories' => 20000, 'prep_minutes' => 600] as $field => $max) {
            $value = $cells[$field] ?? '';
            if ($value === '') {
                continue;
            }
            if (ctype_digit($value) && (int) $value <= $max) {
                $row[$field] = (int) $value;
            } else {
                $errors[] = __('menu.import.bad_number', ['column' => $field, 'value' => $value]);
            }
        }

        return [$row, $errors];
    }

    /**
     * Which fields of an existing item the row would change.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, true>
     */
    private function changes(Item $item, array $row): array
    {
        $changes = [];
        $text = fn (array $value) => $value[$this->locale] ?? '';

        if (array_key_exists('sizes', $row)) {
            $current = $item->variants->map(fn (ItemVariant $v) => ['name' => $text($v->name), 'price' => $v->price_minor])->values()->all();
            if ($current !== $row['sizes']) {
                $changes['sizes'] = true;
            }
        } elseif (array_key_exists('price', $row) && ($item->variants->isNotEmpty() || $item->price_minor !== $row['price'])) {
            $changes['price'] = true;
        }
        foreach (['summary', 'description'] as $field) {
            if (array_key_exists($field, $row) && $text($item->{$field}) !== $row[$field]) {
                $changes[$field] = true;
            }
        }
        if (array_key_exists('tags', $row)) {
            $current = $item->tags->map(fn (Tag $t) => $t->id)->sort()->values()->all();
            $wanted = [];
            foreach ((array) $row['tags'] as $name) {
                $wanted[] = $this->resolveTag((string) $name)?->id;
            }
            sort($wanted);
            if ($current !== $wanted) {
                $changes['tags'] = true;
            }
        }
        if (array_key_exists('allergens', $row)) {
            $wanted = $row['allergens'];
            sort($wanted);
            if ($item->allergens() !== $wanted) {
                $changes['allergens'] = true;
            }
        }
        foreach ($this->plainColumns() as $col => $attr) {
            if (array_key_exists($col, $row) && $item->{$attr} !== $row[$col]) {
                $changes[$col] = true;
            }
        }

        return $changes;
    }

    /**
     * Sheet columns copied as they are; "sold out" only while that feature is on
     * (off: the marks are kept, the column is ignored).
     *
     * @return array<string, string>
     */
    private function plainColumns(): array
    {
        return array_filter(['visible' => 'is_visible', 'sold_out' => 'is_sold_out', 'calories' => 'calories', 'prep_minutes' => 'prep_minutes'],
            fn (string $attr) => $attr !== 'is_sold_out' || Features::here('sold_out'));
    }

    /** @param array<string, mixed> $row */
    private function write(Item $item, array $row): void
    {
        $fields = [];
        foreach (['summary', 'description'] as $field) {
            if (array_key_exists($field, $row)) {
                $fields[$field] = [...$item->{$field}, $this->locale => $row[$field]];
            }
        }
        foreach ($this->plainColumns() as $col => $attr) {
            if (array_key_exists($col, $row)) {
                $fields[$attr] = $row[$col];
            }
        }

        if (array_key_exists('sizes', $row)) {
            $byName = $item->variants->keyBy(fn (ItemVariant $v) => mb_strtolower($v->name[$this->locale] ?? ''));
            $keep = [];
            foreach ($row['sizes'] as $sort => $size) {
                $variant = $byName->get(mb_strtolower($size['name']));
                $values = ['price_minor' => $size['price'], 'sort' => $sort];
                if ($variant) {
                    $variant->forceFill($values)->save();
                } else {
                    $variant = $item->variants()->create([...$values, 'name' => [$this->locale => $size['name']]]);
                }
                $keep[] = $variant->id;
            }
            ItemVariant::where('item_id', $item->id)->whereNotIn('id', $keep)->delete();
            $fields['price_minor'] = $row['sizes'] === [] ? ($row['price'] ?? $item->price_minor) : null;
        } elseif (array_key_exists('price', $row)) {
            ItemVariant::where('item_id', $item->id)->delete();
            $fields['price_minor'] = $row['price'];
        }

        $item->fill($fields)->save();

        if (array_key_exists('tags', $row)) {
            $ids = [];
            foreach ($row['tags'] as $name) {
                $tag = $this->resolveTag($name) ?? $this->createTag($name);
                $ids[$tag->id] = ['tenant_id' => $item->tenant_id];
            }
            $item->tags()->sync($ids);
        }
        if (array_key_exists('allergens', $row)) {
            ItemAllergen::where('item_id', $item->id)->delete();
            foreach ($row['allergens'] as $allergen) {
                ItemAllergen::create(['item_id' => $item->id, 'allergen' => $allergen]);
            }
        }
    }

    private function load(): void
    {
        $this->locale = $this->current->get()->default_locale ?? 'en';
        SystemTags::ensure();
        $this->menus = Menu::with(['categories.items.variants', 'categories.items.tags', 'categories.items.allergenRows'])->get();
        $this->tags = Tag::all();
    }

    private function findMenu(string $name): ?Menu
    {
        $needle = mb_strtolower($name);

        return $this->menus->first(fn (Menu $m) => mb_strtolower($m->name[$this->locale] ?? '') === $needle);
    }

    private function findCategory(string $menu, string $name): ?Category
    {
        $needle = mb_strtolower($name);

        return $this->findMenu($menu)?->categories->first(fn (Category $c) => mb_strtolower($c->name[$this->locale] ?? '') === $needle);
    }

    /** @param array<string, mixed> $row */
    private function findItem(array $row): ?Item
    {
        $needle = mb_strtolower($row['item']);

        return $this->findCategory($row['menu'], $row['category'])?->items->first(fn (Item $i) => mb_strtolower($i->name[$this->locale] ?? '') === $needle);
    }

    private function createMenu(string $name): Menu
    {
        $base = Str::slug($name) ?: 'menu';
        $slug = $base;
        for ($n = 2; Menu::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }
        $menu = Menu::create(['name' => [$this->locale => $name], 'slug' => $slug, 'sort' => ((int) Menu::max('sort')) + 1]);
        $menu->setRelation('categories', new Collection);
        $this->menus->push($menu);

        return $menu;
    }

    private function createCategory(Menu $menu, string $name): Category
    {
        $category = Category::create(['menu_id' => $menu->id, 'name' => [$this->locale => $name], 'sort' => ((int) $menu->categories()->max('sort')) + 1]);
        $category->setRelation('items', new Collection);
        $menu->categories->push($category);

        return $category;
    }

    private function resolveTag(string $name): ?Tag
    {
        $needle = mb_strtolower(trim($name));
        $key = self::TAG_ALIASES[$needle] ?? str_replace([' ', '-'], '_', $needle);

        return $this->tags->first(fn (Tag $t) => $t->key === $key)
            ?? $this->tags->first(fn (Tag $t) => $t->key === null && mb_strtolower($t->name[$this->locale] ?? '') === $needle);
    }

    private function createTag(string $name): Tag
    {
        $tag = Tag::create(['name' => [$this->locale => $name], 'sort' => ((int) Tag::whereNull('key')->max('sort')) + 1]);
        $this->tags->push($tag);

        return $tag;
    }

    private function allergen(string $name): ?string
    {
        $needle = mb_strtolower(trim($name));
        $key = self::ALLERGEN_ALIASES[$needle] ?? $needle;

        return in_array($key, config('menu.allergens'), true) ? $key : null;
    }

    /** "120", "120.50", "120,5", "Br 120" → minor units; null if not a price. */
    private function money(string $text): ?int
    {
        $clean = str_replace(',', '.', preg_replace('/[^\d.,]/', '', $text) ?? '');
        if (! preg_match('/^\d{1,7}(\.\d{1,2})?$/', $clean)) {
            return null;
        }

        return (int) round(((float) $clean) * 100);
    }

    private function key(string ...$parts): string
    {
        return implode("\u{1F}", array_map('mb_strtolower', $parts));
    }

    /**
     * Rows for export, in the same format as the import.
     *
     * @return list<array<string, string|int|null>>
     */
    public function exportRows(): array
    {
        $this->load();
        $text = fn (array $value): string => $value[$this->locale] ?? (string) (reset($value) ?: '');
        $money = fn (?int $minor) => $minor === null ? '' : ($minor % 100 === 0 ? (string) intdiv($minor, 100) : number_format($minor / 100, 2, '.', ''));

        $rows = [];
        foreach ($this->menus as $menu) {
            foreach ($menu->categories as $category) {
                foreach ($category->items as $item) {
                    $rows[] = [
                        'menu' => $text($menu->name),
                        'category' => $text($category->name),
                        'item' => $text($item->name),
                        'price' => $item->variants->isEmpty() ? $money($item->price_minor) : '',
                        'sizes' => $item->variants->map(fn (ItemVariant $v) => $text($v->name).'='.$money($v->price_minor))->implode('; '),
                        'summary' => $text($item->summary),
                        'description' => $text($item->description),
                        'tags' => $item->tags->map(fn (Tag $t) => $t->key ?? $text($t->name))->implode(', '),
                        'allergens' => implode(', ', $item->allergens()),
                        'visible' => $item->is_visible ? 'yes' : 'no',
                        'sold_out' => $item->is_sold_out ? 'yes' : 'no',
                        'calories' => $item->calories,
                        'prep_minutes' => $item->prep_minutes,
                    ];
                }
            }
        }

        return $rows;
    }
}
