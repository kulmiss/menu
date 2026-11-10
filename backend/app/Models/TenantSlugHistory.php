<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'old_slug'])]
class TenantSlugHistory extends Model
{
    protected $table = 'tenant_slug_history';
}
