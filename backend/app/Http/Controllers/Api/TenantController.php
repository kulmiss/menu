<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Tenancy\CurrentTenant;

class TenantController extends Controller
{
    public function show(CurrentTenant $current): TenantResource
    {
        return new TenantResource($current->get(), $current->membership());
    }
}
