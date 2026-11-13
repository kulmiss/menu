<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Menu;
use App\Support\Import\ImportPlanner;
use App\Support\Import\MenuSheet;
use App\Support\PlanLimits;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Menu import (upload → preview → confirm) and export, as CSV or XLSX
 * (PLAN.md §7.2.5). The preview is kept for 30 minutes under a token;
 * confirming re-checks it against the current menu before saving.
 */
class ImportController extends Controller
{
    use RecordsChanges;

    private const TTL_MINUTES = 30;

    public function __construct(
        private readonly CurrentTenant $current,
        private readonly MenuSheet $sheet,
        private readonly ImportPlanner $planner,
    ) {}

    public function template(Request $request): BinaryFileResponse
    {
        Gate::authorize('menu.manage');

        return $this->download($this->format($request), 'menu-template', [
            ['menu' => 'Breakfast', 'category' => 'Coffee', 'item' => 'Macchiato', 'price' => '45', 'summary' => 'Espresso with a little milk', 'tags' => 'popular', 'allergens' => 'milk', 'visible' => 'yes', 'sold_out' => 'no'],
            ['menu' => 'Breakfast', 'category' => 'Coffee', 'item' => 'Latte', 'sizes' => 'Small=60; Large=80', 'tags' => 'vegetarian', 'allergens' => 'milk', 'calories' => 180, 'prep_minutes' => 5],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        Gate::authorize('menu.manage');

        return $this->download($this->format($request), 'menu-'.$this->current->get()->slug, $this->planner->exportRows());
    }

    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('menu.manage');
        $request->validate(['file' => ['required', 'file', 'max:5120']]);
        $file = $request->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            throw ValidationException::withMessages(['file' => __('menu.import.file_type')]);
        }

        try {
            $rows = $this->sheet->read((string) $file->getRealPath(), $extension);
        } catch (RuntimeException $e) {
            $message = match (true) {
                $e->getMessage() === 'too_many' => __('menu.import.too_many', ['max' => MenuSheet::MAX_ROWS]),
                str_starts_with($e->getMessage(), 'columns:') => __('menu.import.missing_columns', ['columns' => substr($e->getMessage(), 8)]),
                default => __('menu.import.unreadable'),
            };
            throw ValidationException::withMessages(['file' => $message]);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => __('menu.import.unreadable')]);
        }

        $plan = $this->planner->plan($rows);
        $token = Str::random(32);
        Cache::put($this->cacheKey($token), $rows, now()->addMinutes(self::TTL_MINUTES));

        return response()->json(['data' => [
            'token' => $token,
            'summary' => $plan['summary'],
            'rows' => $plan['rows'],
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->toIso8601String(),
        ]]);
    }

    public function commit(Request $request, PlanLimits $limits): JsonResponse
    {
        Gate::authorize('menu.manage');
        $data = $request->validate(['token' => ['required', 'string', 'size:32']]);
        $rows = Cache::get($this->cacheKey($data['token']));
        if (! is_array($rows)) {
            throw ValidationException::withMessages(['token' => __('menu.import.expired')]);
        }

        // Check again: the menu may have changed since the preview.
        $plan = $this->planner->plan($rows);
        if ($plan['summary']['errors'] > 0) {
            throw ValidationException::withMessages(['token' => __('menu.import.has_errors')]);
        }
        $limits->ensureRoom('items', Item::count(), $plan['summary']['create']);
        $limits->ensureRoom('menus', Menu::count(), $plan['summary']['new_menus']);

        DB::transaction(fn () => $this->planner->apply($plan['normalized']));
        Cache::forget($this->cacheKey($data['token']));
        $this->recordChange('menu.imported', null, $plan['summary']);

        return response()->json(['data' => ['summary' => $plan['summary']]]);
    }

    private function format(Request $request): string
    {
        return $request->validate(['format' => ['sometimes', Rule::in(['csv', 'xlsx'])]])['format'] ?? 'xlsx';
    }

    /** @param list<array<string, string|int|null>> $rows */
    private function download(string $format, string $name, array $rows): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'menu-export-');
        if ($path === false) {
            throw new RuntimeException('No temporary file.');
        }
        $format === 'csv' ? $this->sheet->writeCsv($path, $rows) : $this->sheet->writeXlsx($path, $rows);

        return response()->download($path, "{$name}.{$format}", [
            'Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function cacheKey(string $token): string
    {
        return 'menu-import:'.$this->current->id().':'.$token;
    }
}
