<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Support\ActivityLogger;
use App\Support\Campaigns\Audience;
use App\Support\Campaigns\Body;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/** Super Admin → Messages: e-mails to restaurant owners (owner, 2026-11-13). */
class CampaignController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(): JsonResponse
    {
        $counts = DB::table('campaign_recipients')->select('campaign_id', 'status', DB::raw('COUNT(*) as n'))->groupBy('campaign_id', 'status')->get()->groupBy('campaign_id');

        return response()->json(['data' => Campaign::latest('id')->limit(200)->get()->map(fn (Campaign $c) => $this->row($c, $counts[$c->id] ?? collect()))]);
    }

    public function show(int $campaign): JsonResponse
    {
        $c = Campaign::findOrFail($campaign);
        $counts = DB::table('campaign_recipients')->where('campaign_id', $c->id)->select('campaign_id', 'status', DB::raw('COUNT(*) as n'))->groupBy('campaign_id', 'status')->get();

        return response()->json(['data' => [
            ...$this->row($c, $counts),
            'body' => $c->body,
            'list' => $c->recipients()->orderByRaw("FIELD(status, 'failed', 'pending', 'sent')")->orderBy('id')->limit(2000)->get()
                ->map(fn (CampaignRecipient $r) => ['id' => $r->id, 'email' => $r->email, 'name' => $r->name, 'restaurant' => $r->vars['restaurant_name'] ?? '', 'status' => $r->status, 'attempts' => $r->attempts, 'error' => $r->error, 'sent_at' => $r->sent_at?->toIso8601String()]),
        ]]);
    }

    /** How many owners the filters reach (before sending). */
    public function count(Request $request): JsonResponse
    {
        return response()->json(['data' => ['count' => collect(Audience::recipients($this->filters($request)))->count()]]);
    }

    /** The e-mail as the first recipient will see it. */
    public function preview(Request $request): JsonResponse
    {
        [$subject, $body] = $this->content($request);
        $first = collect(Audience::recipients($this->filters($request)))->first();
        $vars = $first['vars'] ?? self::sampleVars();

        return response()->json(['data' => [
            'subject' => Body::subject($subject, $vars),
            'html' => (string) Body::html($body, $vars),
            'sample' => $first ? $first['vars']['restaurant_name'] : null,
        ]]);
    }

    /** "Send me a test": to the signed-in Super Admin, now (not queued). */
    public function test(Request $request): JsonResponse
    {
        [$subject, $body] = $this->content($request);
        $vars = collect(Audience::recipients($this->filters($request)))->first()['vars'] ?? self::sampleVars();
        Mail::to($request->user()->email, $request->user()->name)->send(new CampaignMail(__('campaign.test_prefix').$subject, $body, $vars, null));

        return response()->json(['data' => ['sent_to' => $request->user()->email]]);
    }

    public function store(Request $request): JsonResponse
    {
        [$subject, $body] = $this->content($request);
        $filters = $this->filters($request);
        $recipients = collect(Audience::recipients($filters));
        abort_if($recipients->isEmpty(), 422, __('campaign.no_recipients'));

        $campaign = DB::transaction(function () use ($subject, $body, $filters, $recipients, $request) {
            $campaign = Campaign::create(['subject' => $subject, 'body' => $body, 'filters' => $filters, 'status' => 'sending', 'recipients_count' => $recipients->count(), 'created_by' => $request->user()->id]);
            foreach ($recipients->chunk(500) as $chunk) {
                CampaignRecipient::insert($chunk->map(fn ($r) => [...$r, 'vars' => json_encode($r['vars'], JSON_UNESCAPED_UNICODE), 'campaign_id' => $campaign->id, 'status' => 'pending', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()])->all());
            }

            return $campaign;
        });
        $this->activity->log('platform.campaign_sent', $campaign, ['recipients' => ['to' => $recipients->count()]], null);

        return response()->json(['data' => $this->row($campaign, collect([(object) ['status' => 'pending', 'n' => $recipients->count()]]))], 201);
    }

    /** Puts the failed addresses back in the queue. */
    public function retry(int $campaign): JsonResponse
    {
        $c = Campaign::findOrFail($campaign);
        $n = $c->recipients()->where('status', 'failed')->update(['status' => 'pending', 'attempts' => 0, 'error' => null]);
        if ($n > 0) {
            $c->forceFill(['status' => 'sending', 'finished_at' => null])->save();
        }

        return response()->json(['data' => ['requeued' => $n]]);
    }

    public function rate(Request $request): JsonResponse
    {
        $data = $request->validate(['campaign_rate' => ['required', 'integer', 'between:1,500']]);
        PlatformSettings::save($data);

        return response()->json(['data' => $data]);
    }

    /** @return array<string, string> */
    public static function sampleVars(): array
    {
        return ['restaurant_name' => 'Buna & Bread', 'owner_name' => 'Selam', 'plan' => 'Free', 'menu_link' => url('/demo')];
    }

    /** @return array{0: string, 1: string} */
    private function content(Request $request): array
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        return [trim($data['subject']), trim($data['body'])];
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'filters' => ['sometimes', 'array'],
            'filters.plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')],
            'filters.status' => ['nullable', Rule::in(Audience::STATUSES)],
            'filters.tag_id' => ['nullable', 'integer', Rule::exists('platform_tags', 'id')],
            'filters.inactive_days' => ['nullable', 'integer', 'between:1,3650'],
            'filters.signed_up_from' => ['nullable', 'date_format:Y-m-d'],
            'filters.signed_up_to' => ['nullable', 'date_format:Y-m-d'],
            // Restaurants picked in the restaurants table (QUESTIONS #185).
            'filters.restaurant_ids' => ['nullable', 'array', 'max:1000'],
            'filters.restaurant_ids.*' => ['integer'],
        ]);

        return array_filter((array) ($data['filters'] ?? []), fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  iterable<object>  $counts  rows with "status" and "n"
     * @return array<string, mixed>
     */
    private function row(Campaign $c, iterable $counts): array
    {
        $by = collect($counts)->pluck('n', 'status');

        return [
            'id' => $c->id,
            'subject' => $c->subject,
            'status' => $c->status,
            'filters' => $c->filters,
            'recipients' => $c->recipients_count,
            'sent' => (int) ($by['sent'] ?? 0),
            'failed' => (int) ($by['failed'] ?? 0),
            'pending' => (int) ($by['pending'] ?? 0),
            'created_at' => $c->created_at->toIso8601String(),
            'finished_at' => $c->finished_at?->toIso8601String(),
        ];
    }
}
