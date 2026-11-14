<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\SecurityNotice;
use App\Support\Security\SecurityLog;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules($user->email),
        ])->validateWithBag('updatePassword');

        $user->forceFill(['password' => $input['password'], 'password_weak' => false])->save();
        // Other browsers sign out through AuthenticateSession; the app on every device here.
        $user->revokeAppTokens();
        $user->notify(new SecurityNotice('password_changed'));
        SecurityLog::event('password.changed', $user->id);
    }
}
