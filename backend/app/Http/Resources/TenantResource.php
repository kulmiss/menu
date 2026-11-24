<?php

namespace App\Http\Resources;

use App\Models\Membership;
use App\Models\Tenant;
use App\Support\Features;
use App\Support\MyOrder;
use App\Support\UpgradeContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenant */
class TenantResource extends JsonResource
{
    public function __construct(Tenant $tenant, private readonly ?Membership $membership = null)
    {
        parent::__construct($tenant);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $state = $this->subscriptionState();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'default_locale' => $this->default_locale,
            'currency_code' => $this->currency_code,
            'subscription' => [
                'state' => $state->value,
                'allows_writes' => $state->allowsWrites(),
                'shows_banner' => $state->showsBanner(),
                'stage_ends_at' => $this->subscriptionStageEndsAt()?->toIso8601String(),
            ],
            'features' => Features::for($this->resource),
            // The plan's branch limit (null = unlimited), so the admin can hide "+ New branch"
            // once the restaurant is at its limit (owner, 2026-11-24).
            'branch_limit' => $this->resource->plan?->limits['branches'] ?? null,
            // "My order" for guests (platform, plan and the restaurant's own switch) and why it is off (QUESTIONS #236).
            'my_order' => MyOrder::state($this->resource),
            // Where the "Upgrade" buttons point (QUESTIONS #184).
            'upgrade' => UpgradeContact::payload(),
            'membership' => $this->membership ? [
                'id' => $this->membership->id,
                'role' => $this->membership->role->value,
                'can_edit_prices' => $this->membership->canEditPrices(),
            ] : null,
        ];
    }
}
