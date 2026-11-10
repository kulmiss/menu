<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Api\Menu\RecordsChanges;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\PublicMenu\PageTheme;
use App\Support\Qr\QrImages;
use App\Support\Qr\TableCards;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * QR codes screen (PLAN.md screen 8): list, extra codes, downloads (SVG,
 * PNG, PDF card) and table-card sheets. Codes are never reused.
 */
class QrCodeController extends Controller
{
    use RecordsChanges;

    public function __construct(private readonly CurrentTenant $current) {}

    public function index(): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $codes = QrCode::with(['branch', 'table'])->orderBy('id')->get();

        return response()->json(['data' => $codes->map(fn (QrCode $q) => $this->row($q))]);
    }

    /** An extra code (e.g. for a flyer or the window), for the restaurant or a branch. */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'branch_id' => ['sometimes', 'nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $this->current->id())],
        ]);
        $code = QrCode::create([...$data, 'code' => QrCode::newCode()]);
        $this->recordChange('qr.created', $code);

        return response()->json(['data' => $this->row($code->load(['branch', 'table']))], 201);
    }

    public function update(Request $request, Tenant $tenant, int $qr): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $code = QrCode::findOrFail($qr);
        $code->fill($request->validate(['label' => ['present', 'nullable', 'string', 'max:60']]))->save();
        $this->recordChange('qr.updated', $code);

        return response()->json(['data' => $this->row($code->load(['branch', 'table']))]);
    }

    /** Only extra codes; table and branch codes go with their table or branch. */
    public function destroy(Tenant $tenant, int $qr): Response
    {
        Gate::authorize('restaurant.manage');
        $code = QrCode::findOrFail($qr);
        abort_if($code->dining_table_id !== null || ($code->label === null), 422, __('qr.cannot_delete'));
        $code->delete();
        $this->recordChange('qr.deleted', $code);

        return response()->noContent();
    }

    public function download(Tenant $tenant, int $qr, string $format): Response
    {
        Gate::authorize('restaurant.manage');
        $code = QrCode::with(['branch', 'table.branch'])->findOrFail($qr);
        $url = QrImages::url($code);
        $name = Str::slug($tenant->name.' '.$this->describe($code)) ?: 'qr';

        return match ($format) {
            'svg' => $this->file(QrImages::svg($url), 'image/svg+xml', $name.'.svg'),
            'png' => $this->file(QrImages::png($url), 'image/png', $name.'.png'),
            default => $this->file(TableCards::pdf([$this->card($tenant, $code)], $this->colour(), sheet: false), 'application/pdf', $name.'.pdf'),
        };
    }

    /** Printable sheet: four cards per A4 page, for all (or chosen) tables of a branch. */
    public function tableCards(Request $request, Tenant $tenant, int $branch): Response
    {
        Gate::authorize('restaurant.manage');
        $model = Branch::findOrFail($branch);
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('tables', ''))));
        $tables = DiningTable::with('qrCode')->where('branch_id', $model->id)
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('sort')->orderBy('id')->get()
            ->filter(fn (DiningTable $t) => $t->qrCode !== null);
        abort_if($tables->isEmpty(), 404);

        $cards = $tables->map(function (DiningTable $t) use ($tenant, $model) {
            $code = $t->qrCode;
            $code->setRelation('table', $t->setRelation('branch', $model));

            return $this->card($tenant, $code);
        })->values()->all();

        return $this->file(TableCards::pdf($cards, $this->colour()), 'application/pdf', Str::slug($tenant->name.' '.$this->text($model->name).' tables').'.pdf');
    }

    /** @return array{title: string, subtitle: string|null, label: string|null, url: string} */
    private function card(Tenant $tenant, QrCode $code): array
    {
        $branch = $code->table !== null ? $code->table->branch : $code->branch;
        $several = Branch::where('is_active', true)->count() > 1;

        return [
            'title' => $tenant->name,
            'subtitle' => $several && $branch ? $this->text($branch->name) : null,
            'label' => $code->table ? __('qr.table', ['label' => $code->table->label]) : $code->label,
            'url' => QrImages::url($code),
        ];
    }

    private function colour(): string
    {
        return PageTheme::from(TenantAppearance::current())['theme_color'];
    }

    /** @param array<string, string>|null $v */
    private function text(?array $v): string
    {
        $v ??= [];
        $locale = $this->current->get()->default_locale;

        return (string) ($v[$locale] ?? (reset($v) ?: ''));
    }

    private function describe(QrCode $q): string
    {
        return match (true) {
            $q->table !== null => __('qr.table', ['label' => $q->table->label]),
            $q->label !== null => $q->label,
            $q->branch !== null => $this->text($q->branch->name),
            default => __('qr.restaurant'),
        };
    }

    /** @return array<string, mixed> */
    private function row(QrCode $q): array
    {
        return [
            'id' => $q->id,
            'code' => $q->code,
            'url' => QrImages::url($q),
            'label' => $q->label,
            'kind' => $q->dining_table_id ? 'table' : ($q->label !== null ? 'extra' : ($q->branch_id ? 'branch' : 'restaurant')),
            'branch_id' => $q->branch_id,
            'dining_table_id' => $q->dining_table_id,
            'description' => $this->describe($q),
        ];
    }

    private function file(string $body, string $type, string $name): Response
    {
        return response($body, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
