<?php

namespace App\Tenancy;

use RuntimeException;

/**
 * Thrown when tenant-owned data is touched without a current tenant.
 * Fails closed: better an error than data from the wrong restaurant.
 */
class MissingTenantException extends RuntimeException
{
    public static function make(string $model): self
    {
        return new self("No current tenant is set while accessing [{$model}].");
    }
}
