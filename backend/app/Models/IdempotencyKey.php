<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['scope', 'key', 'method', 'path', 'request_hash', 'status', 'response_status', 'response_body', 'response_type', 'expires_at'])]
class IdempotencyKey extends Model
{
    public const PENDING = 'pending';

    public const DONE = 'done';

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
