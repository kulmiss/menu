<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Security\RecoveryCodes;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

/** Fortify's two-factor sign-in, checking recovery codes against their hashes (SECURITY_REPORT.md M5). */
class HashedTwoFactorLoginRequest extends TwoFactorLoginRequest
{
    public function validRecoveryCode()
    {
        if (! $this->recovery_code) {
            return null;
        }
        $user = $this->challengedUser();
        $code = $user instanceof User ? RecoveryCodes::match($user, (string) $this->recovery_code) : null;
        if ($code !== null) {
            $this->session()->forget('login.id');
        }

        return $code;
    }
}
