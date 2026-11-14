<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, ['password' => $this->passwordRules($user->email)])->validate();

        $user->forceFill(['password' => $input['password'], 'password_weak' => false])->save();
        // Whoever had the old password may have signed in to the app: sign every device out.
        $user->revokeAppTokens();
    }
}
