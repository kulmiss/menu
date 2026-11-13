<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One line of the app's notification inbox (QUESTIONS #189), kept 90 days.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $tenant_id
 * @property string $type
 * @property string $title
 * @property string $body
 * @property string $link
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'tenant_id', 'type', 'title', 'body', 'link'])]
class AppNotification extends Model
{
    public const UPDATED_AT = null;

    public const KEEP_DAYS = 90;

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->id, 'type' => $this->type, 'title' => $this->title, 'body' => $this->body,
            'link' => $this->link, 'tenant_id' => $this->tenant_id,
            'read' => $this->read_at !== null, 'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
