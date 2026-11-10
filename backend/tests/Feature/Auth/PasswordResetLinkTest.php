<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('links the password reset email to the SPA reset page', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'reset@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.com'], idem())->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
        $url = $n->toMail($user)->actionUrl;

        return str_starts_with($url, url('/auth/reset-password?token='))
            && str_contains($url, 'email=reset%40example.com');
    });
});
