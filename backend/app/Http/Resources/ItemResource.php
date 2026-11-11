<?php

namespace App\Http\Resources;

use App\Models\AddonGroup;
use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Item */
class ItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new ItemSummaryResource($this->resource))->toArray($request),
            'description' => (object) $this->description,
            'calories' => $this->calories,
            'prep_minutes' => $this->prep_minutes,
            'new_until' => $this->new_until?->toDateString(),
            'variants' => $this->variants->map(fn (ItemVariant $v) => [
                'id' => $v->id,
                'name' => (object) $v->name,
                'price_minor' => $v->price_minor,
                'is_default' => $v->is_default,
                'sort' => $v->sort,
            ])->values(),
            'addon_group_ids' => $this->addonGroups->map(fn (AddonGroup $g) => $g->id)->values(),
            'tag_ids' => $this->tags->pluck('id')->values(),
            'allergens' => $this->allergens(),
            'menu_id' => $this->category->menu_id,
            // All photos in order; the first is the main one (= media).
            'photos' => MediaResource::collection($this->photos)->resolve($request),
        ];
    }
}
