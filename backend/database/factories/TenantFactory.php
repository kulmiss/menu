<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::limit(Str::slug($name), 40, '').'-'.Str::lower(Str::random(4)),
            'trial_ends_at' => now()->addDays(14),
            'timezone' => 'Africa/Addis_Ababa',
            'default_locale' => 'en',
            'currency_code' => 'ETB',
        ];
    }

    /** Subscription ended the given number of days ago. */
    public function expired(int $daysAgo): static
    {
        return $this->state(fn () => [
            'trial_ends_at' => now()->subDays($daysAgo),
            'paid_until' => null,
        ]);
    }
}
