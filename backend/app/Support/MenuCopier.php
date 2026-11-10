<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemAllergen;
use App\Models\Menu;
use Illuminate\Support\Facades\DB;

/**
 * Deep copies for "Duplicate": a menu with its categories, a category with
 * its items, an item with its sizes, add-on links, tags and allergens.
 * Callers wrap these in a transaction.
 */
class MenuCopier
{
    public function itemCount(Menu|Category $source): int
    {
        return $source instanceof Menu
            ? Item::whereIn('category_id', $source->categories()->select('id'))->count()
            : $source->items()->count();
    }

    /** @param array<string, mixed> $overrides */
    public function copyMenu(Menu $source, array $overrides): Menu
    {
        $copy = $source->replicate()->fill($overrides);
        $copy->save();

        foreach ($source->schedules as $schedule) {
            $copy->schedules()->create($schedule->only(['weekday', 'starts_at', 'ends_at']));
        }
        foreach ($source->categories as $category) {
            $this->copyCategory($category, ['menu_id' => $copy->id]);
        }

        return $copy;
    }

    /** @param array<string, mixed> $overrides */
    public function copyCategory(Category $source, array $overrides): Category
    {
        $copy = $source->replicate()->fill($overrides);
        $copy->save();

        foreach ($source->items()->with(['variants', 'addonGroups', 'tags', 'allergenRows'])->get() as $item) {
            $this->copyItem($item, ['category_id' => $copy->id]);
        }

        return $copy;
    }

    /** @param array<string, mixed> $overrides */
    public function copyItem(Item $source, array $overrides): Item
    {
        $source->loadMissing(['variants', 'addonGroups', 'tags', 'allergenRows']);
        $copy = $source->replicate()->fill($overrides);
        $copy->save();

        foreach ($source->variants as $variant) {
            $copy->variants()->create($variant->only(['name', 'price_minor', 'is_default', 'sort']));
        }
        $links = DB::table('item_addon_group')->where('item_id', $source->id)->get();
        foreach ($links as $link) {
            $copy->addonGroups()->attach((int) $link->addon_group_id, [
                'tenant_id' => $copy->tenant_id,
                'sort' => (int) $link->sort,
            ]);
        }
        $copy->tags()->attach($source->tags->mapWithKeys(fn ($t) => [
            $t->id => ['tenant_id' => $copy->tenant_id],
        ])->all());
        foreach ($source->allergens() as $allergen) {
            ItemAllergen::create(['item_id' => $copy->id, 'allergen' => $allergen]);
        }

        return $copy;
    }
}
