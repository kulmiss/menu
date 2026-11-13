<?php

namespace App\Http\Resources;

use App\Models\AddonGroup;
use App\Models\AddonOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AddonGroup */
class AddonGroupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => (object) $this->name,
            'min_choices' => $this->min_choices,
            'max_choices' => $this->max_choices,
            'sort' => $this->sort,
            'options' => $this->options->map(fn (AddonOption $o) => [
                'id' => $o->id,
                'name' => (object) $o->name,
                'price_minor' => $o->price_minor,
                'is_sold_out' => $o->is_sold_out,
                'sort' => $o->sort,
            ])->values(),
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
