<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Super Admin note on a restaurant (CRM timeline). Platform only: never
 * shown to the restaurant.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $author_id
 * @property string $body
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $author
 */
#[Fillable(['tenant_id', 'author_id', 'body'])]
class RestaurantNote extends Model
{
    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
