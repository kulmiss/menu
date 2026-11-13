<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Api\Menu\RecordsChanges;
use App\Http\Controllers\Controller;
use App\Models\Closure;
use App\Models\OpeningHour;
use App\Models\Tenant;
use App\Models\TenantProfile;
use App\Rules\Translations;
use App\Support\ActivityLogger;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Restaurant info screen (PLAN.md screen 10): profile and contact, Wi-Fi,
 * opening hours (restaurant-wide or per branch) and holiday closures.
 */
class InfoController extends Controller
{
    use RecordsChanges;

    private const TIME = 'regex:/^([01]\d|2[0-3]):[0-5]\d$/';

    public function __construct(private readonly CurrentTenant $current) {}

    public function profile(): JsonResponse
    {
        Gate::authorize('restaurant.manage');

        return response()->json(['data' => $this->profileRow(TenantProfile::current())]);
    }

    public function saveProfile(Request $request): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $data = $request->validate([
            'description' => ['sometimes', Translations::optional(1000)],
            'address' => ['sometimes', Translations::optional(300)],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'maps_url' => ['sometimes', 'nullable', 'url:https', 'max:500'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[0-9+() \-]{5,30}$/'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ]{6,20}$/'],
            'telegram' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^@?[A-Za-z0-9_]{5,32}$/'],
            'email' => ['sometimes', 'nullable', 'email', 'max:120'],
            'socials' => ['sometimes', 'nullable', 'array:'.implode(',', TenantProfile::SOCIALS)],
            'socials.*' => ['nullable', 'url:https', 'max:300'],
            'wifi_name' => ['sometimes', 'nullable', 'string', 'max:64'],
            'wifi_password' => ['sometimes', 'nullable', 'string', 'max:64'],
            'show_wifi' => ['sometimes', 'boolean'],
        ], [], ['maps_url' => 'map link', 'socials.*' => 'link']);
        if (isset($data['socials'])) {
            $data['socials'] = array_filter($data['socials'], fn ($v) => $v !== null && $v !== '');
        }
        if (isset($data['telegram'])) {
            $data['telegram'] = ltrim((string) $data['telegram'], '@');
        }

        $profile = TenantProfile::first() ?? new TenantProfile;
        $profile->fill($data)->save();
        $this->recordChange('profile.updated', $profile, ActivityLogger::diff($profile));

        return response()->json(['data' => $this->profileRow($profile)]);
    }

    /** The week's windows for the restaurant (branch_id absent) or one branch. */
    public function hours(Request $request): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $branch = $this->branchParam($request);

        return response()->json(['data' => $this->hoursRows($branch)]);
    }

    /** Replaces the week. For a branch, an empty list = use the restaurant's hours. */
    public function saveHours(Request $request): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $branch = $this->branchParam($request);
        $data = $request->validate([
            'hours' => ['present', 'array', 'max:28'],
            'hours.*.weekday' => ['required', 'integer', 'between:0,6'],
            'hours.*.opens_at' => ['required', 'string', self::TIME],
            'hours.*.closes_at' => ['required', 'string', self::TIME, 'different:hours.*.opens_at'],
        ]);
        $this->checkOverlaps($data['hours']);

        DB::transaction(function () use ($branch, $data) {
            OpeningHour::where('branch_id', $branch)->delete();
            foreach ($data['hours'] as $row) {
                OpeningHour::create(['branch_id' => $branch, ...$row]);
            }
        });
        $this->recordChange('hours.updated', null, ['branch_id' => ['to' => $branch]]);

        return response()->json(['data' => $this->hoursRows($branch)]);
    }

    public function closures(): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $rows = Closure::where('ends_on', '>=', now($this->current->get()?->timezone)->toDateString())->orderBy('starts_on')->get();

        return response()->json(['data' => $rows->map(fn (Closure $c) => $this->closureRow($c))]);
    }

    public function addClosure(Request $request): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $data = $request->validate([
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before:'.now()->addYears(2)->toDateString()],
            'branch_id' => ['sometimes', 'nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $this->current->id())],
            'note' => ['sometimes', Translations::optional(120)],
        ]);
        $closure = Closure::create($data);
        $this->recordChange('closure.created', $closure);

        return response()->json(['data' => $this->closureRow($closure)], 201);
    }

    public function deleteClosure(Tenant $tenant, int $closure): Response
    {
        Gate::authorize('restaurant.manage');
        $model = Closure::findOrFail($closure);
        $model->delete();
        $this->recordChange('closure.deleted', $model);

        return response()->noContent();
    }

    private function branchParam(Request $request): ?int
    {
        $request->validate(['branch_id' => ['sometimes', 'nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $this->current->id())]]);

        return $request->filled('branch_id') ? $request->integer('branch_id') : null;
    }

    /**
     * Windows on the same day may not overlap (an overnight window counts up
     * to midnight on its first day).
     *
     * @param  list<array{weekday: int, opens_at: string, closes_at: string}>  $rows
     */
    private function checkOverlaps(array $rows): void
    {
        $byDay = [];
        foreach ($rows as $i => $row) {
            $end = $row['closes_at'] <= $row['opens_at'] ? '24:00' : $row['closes_at'];
            foreach ($byDay[$row['weekday']] ?? [] as [$start, $stop]) {
                if ($row['opens_at'] < $stop && $start < $end) {
                    throw ValidationException::withMessages(["hours.$i.opens_at" => __('info.overlap')]);
                }
            }
            $byDay[$row['weekday']][] = [$row['opens_at'], $end];
        }
    }

    /** @return list<array{weekday: int, opens_at: string, closes_at: string}> */
    private function hoursRows(?int $branch): array
    {
        return OpeningHour::where('branch_id', $branch)->orderBy('weekday')->orderBy('opens_at')->get()
            ->map(fn (OpeningHour $h) => ['weekday' => $h->weekday, 'opens_at' => substr($h->opens_at, 0, 5), 'closes_at' => substr($h->closes_at, 0, 5)])
            ->values()->all();
    }

    /** @return array<string, mixed> */
    private function profileRow(TenantProfile $p): array
    {
        return [
            'description' => (object) $p->description,
            'address' => (object) $p->address,
            'lat' => $p->lat,
            'lng' => $p->lng,
            'maps_url' => $p->maps_url,
            'phone' => $p->phone,
            'whatsapp' => $p->whatsapp,
            'telegram' => $p->telegram,
            'email' => $p->email,
            'socials' => (object) ($p->socials ?? []),
            'wifi_name' => $p->wifi_name,
            'wifi_password' => $p->wifi_password,
            'show_wifi' => $p->show_wifi,
        ];
    }

    /** @return array<string, mixed> */
    private function closureRow(Closure $c): array
    {
        return ['id' => $c->id, 'branch_id' => $c->branch_id, 'starts_on' => $c->starts_on->toDateString(), 'ends_on' => $c->ends_on->toDateString(), 'note' => (object) $c->note];
    }
}
