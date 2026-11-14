<?php

namespace App\Actions\Fortify;

use App\Rules\NotCommonPassword;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * New and changed passwords: 10+ characters and not on the local list of
     * common/breached passwords (SECURITY_REPORT.md M3). Existing passwords are
     * not forced to change (owner, 2026-11-14); a weak one only gets a banner.
     *
     * @return array<int, mixed>
     */
    protected function passwordRules(?string $email = null): array
    {
        return ['required', 'string', Password::min(10), 'max:255', new NotCommonPassword($email), 'confirmed'];
    }
}
