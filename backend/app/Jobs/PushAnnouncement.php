<?php

namespace App\Jobs;

use App\Models\PlatformAnnouncement;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Push\Push;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/** A new platform announcement to the owners and managers it is shown to (QUESTIONS #189). */
class PushAnnouncement implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $announcementId) {}

    public function handle(): void
    {
        $a = PlatformAnnouncement::find($this->announcementId);
        if ($a === null) {
            return;
        }
        $title = (string) ($a->title['en'] ?? collect($a->title)->first() ?? '');
        $body = (string) ($a->body['en'] ?? collect($a->body ?? [])->first() ?? '');

        Tenant::query()
            ->when($a->plan_ids !== null, fn ($q) => $q->whereIn('plan_id', $a->plan_ids))
            ->whereNull('signup_state')
            ->select(['id', 'name', 'status'])
            ->chunkById(200, function ($tenants) use ($a, $title, $body) {
                foreach ($tenants as $tenant) {
                    if ($tenant->status === 'suspended') {
                        continue;
                    }
                    $users = User::whereIn('id', DB::table('memberships')->where('tenant_id', $tenant->id)->whereIn('role', ['owner', 'manager'])->select('user_id'))->get();
                    Push::send($users, 'platform_announcement', $title, mb_substr(strip_tags($body), 0, 300), "/r/{$tenant->id}/news/{$a->id}", $tenant->id);
                }
            });
    }
}
