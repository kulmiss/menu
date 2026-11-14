<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Api\Menu\RecordsChanges;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\Features;
use App\Support\PlatformSettings;
use App\Support\Print\PrintMaterials;
use App\Support\PublicMenu\PageTheme;
use App\Support\Qr\QrImages;
use App\Support\Qr\TableCards;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
        $codes = QrCode::with(['branch', 'table.branch'])->orderBy('id')->get();

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

        return response()->json(['data' => $this->row($code->load(['branch', 'table.branch']))], 201);
    }

    public function update(Request $request, Tenant $tenant, int $qr): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $code = QrCode::findOrFail($qr);
        $code->fill($request->validate(['label' => ['present', 'nullable', 'string', 'max:60']]))->save();
        $this->recordChange('qr.updated', $code);

        return response()->json(['data' => $this->row($code->load(['branch', 'table.branch']))]);
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
        $tenant->markQrDownloaded();
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
        $tenant->markQrDownloaded();
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

    /** What the print screen needs to draw its preview and the social-media pictures. */
    public function printInfo(Tenant $tenant): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $appearance = TenantAppearance::current();
        $theme = PageTheme::from($appearance);

        return response()->json(['data' => [
            'name' => $tenant->name,
            'colour' => $theme['theme_color'],
            'logo' => $appearance->logo?->urls()['jpg'] ?? null,
            'headline' => __('qr.print.headline'),
            'scan' => __('qr.print.scan'),
            'branding' => Features::enabled($tenant, 'remove_branding') ? null : __('public.powered', ['app' => PlatformSettings::get('name')]),
        ]]);
    }

    /**
     * Print materials (owner, 2026-11-14, QUESTIONS #179): table tents,
     * A5/A4 posters, window sign and sticker sheets for the chosen codes.
     */
    public function printMaterials(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('restaurant.manage');
        $data = $request->validate([
            'material' => ['required', Rule::in(array_keys(PrintMaterials::PAGES))],
            'design' => ['required', Rule::in(PrintMaterials::DESIGNS)],
            'theme' => ['required', Rule::in(['light', 'dark'])],
            'headline' => ['nullable', 'string', 'max:40'],
            'codes' => ['required', 'string', 'max:2000'],
            'bleed' => ['sometimes', 'boolean'],
            'inline' => ['sometimes', 'boolean'],
        ]);
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $data['codes'])))));
        abort_if($ids === [] || count($ids) > 200, 422, __('qr.print.codes'));
        $found = QrCode::with(['branch', 'table.branch'])->whereIn('id', $ids)->get()->keyBy('id');
        abort_if($found->count() !== count($ids), 404);
        $tenant->markQrDownloaded();

        $appearance = TenantAppearance::current();
        $logo = $appearance->logo;
        $logoFile = $logo !== null && isset($logo->variants['jpg']) ? Storage::disk($logo->disk)->path($logo->base_path.'/'.$logo->variants['jpg']) : null;
        $headline = trim((string) ($data['headline'] ?? '')) ?: __('qr.print.headline');
        $pdf = (new PrintMaterials(
            array_map(fn (int $id) => $this->card($tenant, $found[$id]), $ids),
            [
                'material' => $data['material'],
                'design' => $data['design'],
                'theme' => $data['theme'],
                'headline' => $headline,
                'scan' => __('qr.print.scan'),
                'bleed' => (bool) ($data['bleed'] ?? false),
                'colour' => PageTheme::from($appearance)['theme_color'],
                'logo' => $logoFile !== null && is_file($logoFile) ? $logoFile : null,
                'branding' => Features::enabled($tenant, 'remove_branding') ? null : __('public.powered', ['app' => PlatformSettings::get('name')]),
            ],
        ))->render();
        $name = Str::slug($tenant->name.' '.str_replace('_', ' ', $data['material'])) ?: 'print';
        $response = $this->file($pdf, 'application/pdf', $name.'.pdf');
        if ($request->boolean('inline')) {
            $response->headers->set('Content-Disposition', 'inline; filename="'.$name.'.pdf"');
        }

        return $response;
    }

    /** @return array{title: string, subtitle: string|null, label: string|null, url: string} */
    private function card(Tenant $tenant, QrCode $code): array
    {
        $branch = ($code->table !== null ? $code->table->branch : $code->branch) ?? $this->mainBranch();
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

    private ?Branch $main = null;

    private function mainBranch(): ?Branch
    {
        return $this->main ??= Branch::orderByDesc('is_default')->orderBy('sort')->orderBy('id')->first();
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
            'branch_id' => $q->branch_id ?? $q->table?->branch_id,
            // Which branch a guest lands on: the code's own, the table's, else the main branch (owner, 2026-11-12).
            'branch' => $this->text(($q->branch ?? $q->table->branch ?? $this->mainBranch())?->name),
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
