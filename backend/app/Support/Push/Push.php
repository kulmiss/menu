<?php

namespace App\Support\Push;

use App\Jobs\SendPush;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sends one notification to users (QUESTIONS #189): writes it to each inbox
 * now, and queues the phone push (sent by the existing cron queue worker)
 * for users who have that type switched on.
 */
class Push
{
    /** @param  iterable<User>|User  $users */
    public static function send(iterable|User $users, string $type, string $title, string $body, string $link, ?int $tenantId = null): void
    {
        $users = $users instanceof User ? [$users] : $users;
        foreach ($users as $user) {
            $note = AppNotification::create([
                'user_id' => $user->id, 'tenant_id' => $tenantId, 'type' => $type,
                'title' => mb_substr($title, 0, 200), 'body' => mb_substr($body, 0, 500), 'link' => mb_substr($link, 0, 255),
            ]);
            if (PushTypes::enabled($user, $type) && $user->pushTokens()->exists()) {
                DB::afterCommit(fn () => SendPush::dispatch($note->id));
            }
        }
    }

    /** @return iterable<User> */
    public static function superAdmins(): iterable
    {
        return User::where('is_super_admin', true)->get();
    }

    /**
     * Owners (and managers, if asked) of a restaurant.
     *
     * @param  list<string>  $roles
     * @return iterable<User>
     */
    public static function team(int $tenantId, array $roles = ['owner']): iterable
    {
        return User::whereIn('id', DB::table('memberships')->where('tenant_id', $tenantId)->whereIn('role', $roles)->select('user_id'))->get();
    }
}
