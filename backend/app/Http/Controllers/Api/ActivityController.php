<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\ActivityLog;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ActivityController extends Controller
{
    public function __invoke(CurrentTenant $current): AnonymousResourceCollection
    {
        Gate::authorize('activity.view');

        return ActivityResource::collection(
            ActivityLog::query()
                ->where('tenant_id', $current->id())
                ->with('actor')
                ->latest('id')
                ->cursorPaginate(50)
        );
    }
}
