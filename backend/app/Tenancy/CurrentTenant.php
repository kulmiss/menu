<?php

namespace App\Tenancy;

use App\Models\Membership;
use App\Models\Tenant;

/**
 * Holds the restaurant the current request works on. Bound as a scoped
 * singleton, so it is reset between requests and queued jobs.
 */
class CurrentTenant
{
    private ?Tenant $tenant = null;

    private ?Membership $membership = null;

    public function set(Tenant $tenant, ?Membership $membership = null): void
    {
        $this->tenant = $tenant;
        $this->membership = $membership;
    }

    public function forget(): void
    {
        $this->tenant = null;
        $this->membership = null;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function membership(): ?Membership
    {
        return $this->membership;
    }

    /**
     * Run a callback as the given tenant, then restore the previous one.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Tenant $tenant, callable $callback): mixed
    {
        [$previousTenant, $previousMembership] = [$this->tenant, $this->membership];
        $this->set($tenant);

        try {
            return $callback();
        } finally {
            $this->tenant = $previousTenant;
            $this->membership = $previousMembership;
        }
    }
}
