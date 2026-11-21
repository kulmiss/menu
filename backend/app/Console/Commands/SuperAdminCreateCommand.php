<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\NotCommonPassword;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * The first (or another) Super Admin, made on the server (owner, 2026-11-19,
 * QUESTIONS #233): name, e-mail, a password typed twice without showing it.
 * Two-factor is set up at the first sign-in (the area asks for it first).
 */
#[Signature('super:create {--name= : Full name} {--email= : E-mail address}')]
#[Description('Create a Super Admin account (asks for the password)')]
class SuperAdminCreateCommand extends Command
{
    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Full name'));
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('E-mail address'))));
        $password = (string) $this->secret('Password (at least 10 characters)');
        $again = (string) $this->secret('Password again');

        $v = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $again],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', Password::min(10), 'max:255', new NotCommonPassword($email), 'confirmed'],
            ],
        );
        if ($v->fails()) {
            foreach ($v->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();

        $this->info("Super Admin {$email} created.");
        $this->line('Next: php artisan super:access shows the sign-in address. At the first sign-in, set up two-factor and keep the recovery codes.');

        return self::SUCCESS;
    }
}
