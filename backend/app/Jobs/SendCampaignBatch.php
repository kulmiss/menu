<?php

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Sends the next campaign e-mails, at most "campaign_rate" per run. The
 * scheduler queues one run a minute; the existing cron queue worker runs it
 * (shared-hosting friendly). A failed address is tried again in later runs,
 * up to 3 attempts, then marked failed.
 */
class SendCampaignBatch implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 120;

    public function handle(): void
    {
        $rate = max(1, min(500, (int) PlatformSettings::get('campaign_rate')));
        $batch = CampaignRecipient::with('campaign')->where('status', 'pending')->orderBy('id')->limit($rate)->get();

        foreach ($batch as $r) {
            // Another worker may have taken it meanwhile: claim it first.
            $claimed = CampaignRecipient::whereKey($r->id)->where('status', 'pending')->where('attempts', $r->attempts)
                ->update(['attempts' => $r->attempts + 1]);
            if ($claimed === 0) {
                continue;
            }
            try {
                $url = $r->user_id ? URL::signedRoute('news.unsubscribe', ['user' => $r->user_id]) : null;
                Mail::to($r->email, $r->name)->send(new CampaignMail($r->campaign->subject, $r->campaign->body, $r->vars, $url));
                $r->forceFill(['status' => 'sent', 'sent_at' => now(), 'error' => null, 'attempts' => $r->attempts + 1])->save();
            } catch (Throwable $e) {
                $failed = $r->attempts + 1 >= CampaignRecipient::MAX_ATTEMPTS;
                $r->forceFill(['status' => $failed ? 'failed' : 'pending', 'error' => mb_substr($e->getMessage(), 0, 300), 'attempts' => $r->attempts + 1])->save();
            }
        }

        self::finish();
    }

    /** Marks campaigns without pending recipients as done. */
    public static function finish(): void
    {
        Campaign::where('status', 'sending')
            ->whereNotExists(fn ($q) => $q->from('campaign_recipients')->whereColumn('campaign_recipients.campaign_id', 'campaigns.id')->where('status', 'pending'))
            ->update(['status' => 'done', 'finished_at' => now()]);
    }

    /** Pending work, so the scheduler only queues a run when needed. */
    public static function pending(): bool
    {
        return DB::table('campaign_recipients')->where('status', 'pending')->exists();
    }
}
