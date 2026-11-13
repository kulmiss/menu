<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the activity log. Tenant and actor come from the current request,
 * so callers only say what happened.
 */
class ActivityLogger
{
    /** Attribute names whose values are never written to the log. */
    private const MASKED = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'token_hash', 'wifi_password'];

    public function __construct(private readonly CurrentTenant $tenant) {}

    /**
     * @param  array<string, mixed>|null  $changes
     */
    public function log(string $action, ?Model $subject = null, ?array $changes = null, ?int $tenantId = null): ActivityLog
    {
        $request = request();

        return ActivityLog::create([
            'tenant_id' => $tenantId ?? $this->tenant->id(),
            'actor_id' => $request->user()?->getAuthIdentifier(),
            // "Log in as" is never recorded (owner, 2026-11-13): changes count as the owner's.
            'impersonator_id' => null,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes === null ? null : $this->mask($changes),
            'ip_hash' => IpHash::make($request->ip()),
        ]);
    }

    /**
     * Before/after values of a model's changed attributes.
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function diff(Model $model): array
    {
        $diff = [];
        foreach ($model->getChanges() as $key => $value) {
            if ($key === 'updated_at') {
                continue;
            }
            $diff[$key] = ['from' => $model->getOriginal($key), 'to' => $value];
        }

        return $diff;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function mask(array $changes): array
    {
        foreach ($changes as $key => $value) {
            if (in_array($key, self::MASKED, true)) {
                $changes[$key] = '***';
            } elseif (is_array($value)) {
                $changes[$key] = $this->mask($value);
            }
        }

        return $changes;
    }
}
