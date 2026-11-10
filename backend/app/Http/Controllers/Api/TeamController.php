<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Membership;
use App\Models\Tenant;
use App\Support\ActivityLogger;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function __construct(private readonly CurrentTenant $current, private readonly ActivityLogger $activity) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('team.view');

        return MemberResource::collection(
            $this->members()->with('user')->orderBy('id')->get()
        );
    }

    public function update(Request $request, Tenant $tenant, int $member): MemberResource
    {
        $membership = $this->members()->with('user')->findOrFail($member);
        $actor = $this->current->membership();

        if ($membership->is($actor)) {
            throw ValidationException::withMessages(['role' => __('menu.team.cannot_change_self')]);
        }

        Gate::authorize('team.manage', $membership->role);

        $data = $request->validate([
            'role' => ['sometimes', Rule::enum(Role::class)],
            'can_edit_prices' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['role'])) {
            Gate::authorize('team.manage', Role::from($data['role']));
        }

        $membership->fill($data)->save();
        $this->activity->log('member.updated', $membership, ActivityLogger::diff($membership));

        return new MemberResource($membership);
    }

    public function destroy(Tenant $tenant, int $member): Response
    {
        $membership = $this->members()->findOrFail($member);
        $leavingSelf = $membership->is($this->current->membership());

        if ($membership->isOwner()) {
            throw ValidationException::withMessages(['member' => __('menu.team.owner_cannot_leave')]);
        }

        if (! $leavingSelf) {
            Gate::authorize('team.manage', $membership->role);
        }

        $membership->delete();
        $this->activity->log($leavingSelf ? 'member.left' : 'member.removed', $membership, ['user_id' => $membership->user_id]);

        return response()->noContent();
    }

    /** @return Builder<Membership> */
    private function members()
    {
        return Membership::query()->where('tenant_id', $this->current->id());
    }
}
