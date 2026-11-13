<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_id' => $this->menu_id,
            'name' => (object) $this->name,
            'description' => (object) $this->description,
            'icon' => $this->icon,
            'media_id' => $this->media_id,
            'media' => $this->whenLoaded('media', fn () => $this->media ? new MediaResource($this->media) : null),
            'is_visible' => $this->is_visible,
            'sort' => $this->sort,
            'items' => ItemSummaryResource::collection($this->whenLoaded('items')),
        ];
    }
}
