<?php

namespace App\Http\Resources;

use App\Models\Item;
use App\Support\Features;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An item in the editor's list: enough for the row (name, price, switches)
 * without the full details.
 *
 * @mixin Item
 */
class ItemSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => (object) $this->name,
            'summary' => (object) $this->summary,
            'price_minor' => $this->price_minor,
            'from_price_minor' => $this->fromPriceMinor(),
            'variants_count' => $this->variants->count(),
            'variants' => $this->variants->map(fn ($v) => ['id' => $v->id, 'name' => (object) $v->name, 'price_minor' => $v->price_minor])->values(),
            'media_id' => $this->media_id,
            'media' => $this->whenLoaded('media', fn () => $this->media ? new MediaResource($this->media) : null),
            'is_sold_out' => $this->is_sold_out && Features::here('sold_out'), // ignored while "sold out" is off
            'is_visible' => $this->is_visible,
            'is_new' => $this->is_new,
            'is_popular' => $this->is_popular,
            'show_photo' => $this->show_photo,
            'sort' => $this->sort,
        ];
    }
}
