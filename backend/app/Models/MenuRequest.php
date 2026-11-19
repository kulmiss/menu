<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "Add my menu for me" (owner, 2026-11-19, QUESTIONS #235): photos of a paper
 * menu sent by a restaurant; the Super Admin types the menu in for them.
 * The photos live outside the public folder (storage/app/private/menu-requests/{id}).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string|null $note
 * @property list<array{name: string, file: string, mime: string, bytes: int}> $files
 * @property string $status
 * @property int|null $handled_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['tenant_id', 'user_id', 'note', 'files', 'status', 'handled_by'])]
class MenuRequest extends Model
{
    public const STATUSES = ['new', 'working', 'done'];

    public const MAX_FILES = 10;

    /** Open requests one restaurant may have at a time. */
    public const MAX_OPEN = 3;

    protected function casts(): array
    {
        return ['files' => 'array'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dir(): string
    {
        return 'menu-requests/'.$this->id;   // on the "local" disk: storage/app/private
    }
}
