<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\TenantSlugHistory;
use App\Rules\ValidSlug;
use App\Support\ActivityLogger;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    /**
     * Change the restaurant's address. The old slug keeps redirecting (and
     * stays reserved for this restaurant), so printed QR codes keep working.
     */
    public function updateSlug(Request $request, CurrentTenant $current, ActivityLogger $activity): TenantResource
    {
        Gate::authorize('account.manage');
        $tenant = $current->get();

        $data = $request->validate(['slug' => ['required', new ValidSlug($tenant->id)]]);
        $old = $tenant->slug;

        if ($data['slug'] !== $old) {
            DB::transaction(function () use ($tenant, $data, $old) {
                TenantSlugHistory::where('tenant_id', $tenant->id)->where('old_slug', $data['slug'])->delete();
                TenantSlugHistory::create(['tenant_id' => $tenant->id, 'old_slug' => $old]);
                $tenant->forceFill(['slug' => $data['slug']])->save();
                $tenant->bumpCacheVersion();
            });

            $activity->log('tenant.slug_changed', $tenant, ['slug' => ['from' => $old, 'to' => $data['slug']]]);
        }

        return new TenantResource($tenant->refresh(), $current->membership());
    }
}
