<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Tenant;
use App\Notifications\InvitationNotification;
use App\Support\ActivityLogger;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function __construct(private readonly CurrentTenant $current, private readonly ActivityLogger $activity) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('team.view');

        return InvitationResource::collection(
            Invitation::query()->whereNull('accepted_at')->where('expires_at', '>', now())->orderBy('id')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(Role::class)],
            'can_edit_prices' => ['sometimes', 'boolean'],
        ]);

        $role = Role::from($data['role']);
        Gate::authorize('team.manage', $role);

        $email = Str::lower($data['email']);

        $alreadyMember = Membership::query()
            ->where('tenant_id', $this->current->id())
            ->whereHas('user', fn ($q) => $q->where('email', $email))
            ->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages(['email' => __('menu.team.already_member')]);
        }

        // A new invitation replaces any open one for the same address.
        Invitation::query()->where('email', $email)->whereNull('accepted_at')->delete();

        $token = Str::random(48);
        $invitation = Invitation::create([
            'email' => $email,
            'role' => $role,
            'can_edit_prices' => $role === Role::Staff && ($data['can_edit_prices'] ?? false),
            'token_hash' => Invitation::hashToken($token),
            'expires_at' => now()->addDays((int) config('menu.invitation_days')),
            'invited_by' => $request->user()->id,
        ]);

        Notification::route('mail', $email)->notify(new InvitationNotification(
            $invitation,
            $token,
            $this->current->get()->name,
            $request->user()->name,
            $this->current->get()->default_locale,
        ));

        $this->activity->log('invitation.created', $invitation, ['email' => $email, 'role' => $role->value]);

        return (new InvitationResource($invitation))->response()->setStatusCode(201);
    }

    public function destroy(Tenant $tenant, int $invitation): Response
    {
        $model = Invitation::query()->findOrFail($invitation);
        Gate::authorize('team.manage', $model->role);

        $model->delete();
        $this->activity->log('invitation.revoked', $model, ['email' => $model->email]);

        return response()->noContent();
    }
}
