<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['email', 'role', 'can_edit_prices', 'token_hash', 'expires_at', 'invited_by'])]
#[Hidden(['token_hash'])]
class Invitation extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'can_edit_prices' => 'boolean',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
