<?php

namespace App\Http\Controllers\Api\Menu;

use App\Support\ActivityLogger;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every menu change is logged and makes the public menu page rebuild
 * (tenants.cache_version, PLAN.md §3.3).
 */
trait RecordsChanges
{
    /**
     * @param  array<string, mixed>|null  $changes
     */
    protected function recordChange(string $action, ?Model $subject = null, ?array $changes = null): void
    {
        app(ActivityLogger::class)->log($action, $subject, $changes);
        app(CurrentTenant::class)->get()?->bumpCacheVersion();
    }

    /**
     * New sort value at the end of a list.
     *
     * @param  Builder<covariant Model>|Relation<covariant Model, covariant Model, mixed>  $siblings
     */
    protected function nextSort($siblings): int
    {
        return ((int) $siblings->max('sort')) + 1;
    }

    /**
     * Saves a new order for a list. `$ids` must be exactly the list's current
     * ids (sending the whole order makes a repeat harmless).
     *
     * @param  Builder<covariant Model>  $siblings
     * @param  array<int|string, mixed>  $ids
     */
    protected function applyOrder($siblings, array $ids): void
    {
        $current = $siblings->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $wanted = collect($ids)->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($current !== $wanted) {
            throw ValidationException::withMessages([
                'ids' => __('menu.menu_editor.reorder_mismatch'),
            ]);
        }

        DB::transaction(function () use ($siblings, $ids) {
            foreach (array_values($ids) as $position => $id) {
                (clone $siblings)->whereKey((int) $id)->update(['sort' => $position]);
            }
        });
    }

    /**
     * Name of a copy in every language: "Breakfast" → "Breakfast (copy)".
     *
     * @param  array<string, string>  $name
     * @return array<string, string>
     */
    protected function copyName(array $name): array
    {
        return collect($name)
            ->map(fn (string $text, string $locale) => __('menu.menu_editor.copy_suffix', ['name' => $text], $locale))
            ->all();
    }
}
