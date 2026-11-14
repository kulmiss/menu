<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\EmailChangeConfirm;
use App\Notifications\EmailChangeNotice;
use App\Support\Security\SecurityLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * Name, language and e-mail (web and app). A new e-mail needs the current
 * password, becomes active only after the link sent to it is clicked, and the
 * old address hears about it (SECURITY_REPORT.md H3).
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        $input['email'] = Str::lower(trim((string) ($input['email'] ?? '')));
        Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'locale' => ['nullable', Rule::in(config('menu.locales'))],
            'current_password' => ['nullable', 'string', 'max:255'],
        ])->validateWithBag('updateProfileInformation');

        $emailChanged = $input['email'] !== Str::lower($user->email);
        if ($emailChanged && ! Hash::check((string) ($input['current_password'] ?? ''), $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('security.email_change.password')])
                ->errorBag('updateProfileInformation');
        }

        $user->forceFill([
            'name' => $input['name'],
            'locale' => $input['locale'] ?? $user->locale,
        ]);

        if (! $emailChanged) {
            // Typing the old address again cancels a pending change.
            $user->pending_email = null;
            $user->save();

            return;
        }

        $user->pending_email = $input['email'];
        $user->save();

        Notification::route('mail', $input['email'])->notify(new EmailChangeConfirm($user->id, $input['email'], $user->name));
        $user->notify(new EmailChangeNotice($input['email'], $user->name));
        SecurityLog::event('email_change.requested', $user->id);
    }

    /** The link from the new address: makes the change once, then the link is spent. */
    public static function confirm(User $user, string $hash): bool
    {
        if ($user->pending_email === null || ! hash_equals(sha1($user->pending_email), $hash)) {
            return false;
        }
        if (User::where('email', $user->pending_email)->whereKeyNot($user->id)->exists()) {
            $user->forceFill(['pending_email' => null])->save();

            return false;
        }

        $user->forceFill(['email' => $user->pending_email, 'pending_email' => null, 'email_verified_at' => now()])->save();
        SecurityLog::event('email_change.confirmed', $user->id);

        return true;
    }
}
