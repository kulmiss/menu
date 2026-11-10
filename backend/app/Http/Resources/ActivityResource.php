<?php

namespace App\Http\Resources;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ActivityLog */
class ActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'subject' => $this->subject_type ? ['type' => $this->subject_type, 'id' => $this->subject_id] : null,
            'changes' => $this->changes,
            'actor' => $this->actor ? ['id' => $this->actor->id, 'name' => $this->actor->name] : null,
            'impersonated' => $this->impersonator_id !== null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
