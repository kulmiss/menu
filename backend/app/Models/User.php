<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property array<string, bool>|null $push_settings Push types switched off (QUESTIONS #189)
 * @property Carbon|null $news_unsubscribed_at News e-mails off (account e-mails still sent)
 */
#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Column defaults, so a freshly created model has them without a reload.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => 'en',
        'is_super_admin' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'password_weak' => 'boolean',
            'last_login_at' => 'datetime',
            'news_unsubscribed_at' => 'datetime',
            'push_settings' => 'array',
        ];
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<PushToken, $this> */
    public function pushTokens(): HasMany
    {
        return $this->hasMany(PushToken::class);
    }

    /**
     * Signs the app out on every device (or every device but one) after a password
     * reset or change (SECURITY_REPORT.md H2). Push tokens go with their sign-in.
     */
    public function revokeAppTokens(?int $exceptTokenId = null): int
    {
        return $this->tokens()->when($exceptTokenId !== null, fn ($q) => $q->whereKeyNot($exceptTokenId))->delete();
    }

    public function membershipFor(Tenant|int $tenant): ?Membership
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return $this->memberships()->where('tenant_id', $tenantId)->first();
    }

    /** "Confirm your e-mail" with a 24-hour link that works signed out (owner, 2026-11-13). */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
