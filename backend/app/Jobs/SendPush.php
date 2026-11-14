<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\PushToken;
use App\Support\Push\FcmClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** One inbox notification to every phone of its user (QUESTIONS #189). */
class SendPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $notificationId) {}

    public function handle(FcmClient $fcm): void
    {
        $note = AppNotification::find($this->notificationId);
        if ($note === null || ! $fcm->configured()) {
            return; // Firebase not set up yet: the inbox still has it.
        }

        $data = ['type' => $note->type, 'link' => $note->link, 'notification_id' => (string) $note->id, 'tenant_id' => (string) ($note->tenant_id ?? '')];
        foreach (PushToken::where('user_id', $note->user_id)->get() as $token) {
            try {
                $result = $fcm->send($token->token, $note->title, $note->body, $data);
            } catch (Throwable $e) {
                Log::warning('Push failed: '.$e->getMessage());

                continue;
            }
            if ($result === FcmClient::GONE) {
                $token->delete();
            }
        }
    }
}
