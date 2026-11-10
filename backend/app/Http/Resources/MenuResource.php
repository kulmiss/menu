<?php

namespace App\Http\Resources;

use App\Models\Menu;
use App\Models\MenuSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Menu */
class MenuResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => (object) $this->name,
            'slug' => $this->slug,
            'schedule_mode' => $this->schedule_mode,
            'is_visible' => $this->is_visible,
            'sort' => $this->sort,
            'schedules' => $this->whenLoaded('schedules', fn () => $this->schedules->map(fn (MenuSchedule $s) => [
                'weekday' => $s->weekday,
                'starts_at' => substr($s->starts_at, 0, 5),
                'ends_at' => substr($s->ends_at, 0, 5),
            ])->values()),
            'categories_count' => $this->whenCounted('categories'),
            'items_count' => $this->when(
                array_key_exists('items_count', $this->resource->getAttributes()),
                fn () => (int) $this->resource->getAttributes()['items_count'],
            ),
        ];
    }
}
