<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a user to a restaurant with a role. It is the bridge used to find a
 * user's restaurants, so it is read by user and by tenant explicitly rather
 * than through the tenant scope.
 */
#[Fillable(['tenant_id', 'user_id', 'role', 'can_edit_prices', 'invited_by'])]
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'can_edit_prices' => 'boolean',
        ];
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

    public function isOwner(): bool
    {
        return $this->role === Role::Owner;
    }

    public function canEditPrices(): bool
    {
        return $this->role !== Role::Staff || $this->can_edit_prices;
    }
}
