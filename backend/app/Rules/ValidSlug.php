<?php

namespace App\Rules;

use App\Models\Tenant;
use App\Models\TenantSlugHistory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A restaurant slug (menu.example.com/<slug>): 3–50 lower-case letters,
 * digits and hyphens, not reserved, and never used by another restaurant —
 * including old slugs, because printed links and QR codes may still use them.
 */
class ValidSlug implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreTenantId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[a-z0-9](?:[a-z0-9-]{1,48})[a-z0-9]$/', $value) || str_contains($value, '--')) {
            $fail('menu.slug.format')->translate();

            return;
        }

        if (in_array($value, config('menu.reserved_slugs'), true)) {
            $fail('menu.slug.reserved')->translate();

            return;
        }

        $taken = Tenant::withTrashed()
            ->where('slug', $value)
            ->when($this->ignoreTenantId, fn ($q, $id) => $q->whereKeyNot($id))
            ->exists()
            || TenantSlugHistory::where('old_slug', $value)
                ->when($this->ignoreTenantId, fn ($q, $id) => $q->where('tenant_id', '!=', $id))
                ->exists();

        if ($taken) {
            $fail('menu.slug.taken')->translate();
        }
    }
}
