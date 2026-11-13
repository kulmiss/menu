<?php

namespace App\Support\Tables;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server side of the shared admin DataTable (owner, 2026-11-13, QUESTIONS #185):
 * search, filters, sorting and pages in SQL, so lists stay fast with 10,000+
 * rows; the same filters export the whole view as CSV.
 *
 * Request: q, page (from 1), per (10|25|50|100), sort ("name" or "-name"),
 * one parameter per filter (comma-separated values), from / to (dates) for the
 * date filter, format=csv for the export.
 * Answer: {data: [...], meta: {total, page, per_page, pages}}.
 */
final class TableQuery
{
    public const PER_PAGE = [10, 25, 50, 100];

    /**
     * Spec keys: search (fn(query, text)), filters (name => fn(query, values)),
     * date (column for from/to), sorts (key => column or fn(query, dir)),
     * default_sort ("-created_at"), csv (name, columns: header => fn(model)).
     *
     * @param  Builder<Model>|EloquentBuilder  $query
     * @param  array<string, mixed>  $spec
     * @param  Closure(mixed): array<string, mixed>  $row
     */
    public static function respond($query, Request $request, array $spec, Closure $row): JsonResponse|StreamedResponse
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per' => ['sometimes', 'integer', 'in:'.implode(',', self::PER_PAGE)],
            'sort' => ['sometimes', 'nullable', 'string', 'max:40'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        self::filter($query, $request, $spec);

        if ($request->query('format') === 'csv' && isset($spec['csv'])) {
            return self::csv($query, $spec['csv']);
        }

        $per = (int) $request->integer('per', 25);
        $per = in_array($per, self::PER_PAGE, true) ? $per : 25;
        $page = max(1, (int) $request->integer('page', 1));
        $total = (clone $query)->toBase()->getCountForPagination();
        $meta = ['total' => $total, 'page' => $page, 'per_page' => $per, 'pages' => max(1, (int) ceil($total / $per))];
        // Sums over the whole filtered view (e.g. payments per currency), not just this page.
        if (isset($spec['totals'])) {
            [$column, $group] = $spec['totals'];
            $meta['totals'] = (clone $query)->reorder()->toBase()->groupBy($group)
                ->selectRaw("{$group} as k, SUM({$column}) as v")->pluck('v', 'k')->map(fn ($v) => (int) $v);
        }
        $rows = $query->forPage($page, $per)->get();

        return response()->json(['data' => $rows->map($row)->values(), 'meta' => $meta]);
    }

    /**
     * Search, filters, date range and order, without paging (also for bulk actions on "all matching").
     *
     * @param  Builder<Model>|EloquentBuilder  $query
     * @param  array<string, mixed>  $spec
     */
    public static function filter($query, Request $request, array $spec): void
    {
        $q = trim((string) $request->query('q', ''));
        if ($q !== '' && isset($spec['search'])) {
            // % and _ are searched as themselves.
            $spec['search']($query, addcslashes($q, '%_\\'));
        }
        foreach ($spec['filters'] ?? [] as $name => $apply) {
            $raw = $request->query($name);
            $values = is_array($raw) ? $raw : explode(',', (string) $raw);
            $values = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $values), fn ($v) => $v !== ''));
            if ($values !== []) {
                $apply($query, array_slice($values, 0, 50));
            }
        }
        if (isset($spec['date'])) {
            if ($from = $request->query('from')) {
                $query->where($spec['date'], '>=', $from.' 00:00:00');
            }
            if ($to = $request->query('to')) {
                $query->where($spec['date'], '<=', $to.' 23:59:59');
            }
        }

        $sort = (string) $request->query('sort', '') ?: $spec['default_sort'];
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $key = ltrim($sort, '-');
        $by = $spec['sorts'][$key] ?? null;
        if ($by === null) {
            $key = ltrim($spec['default_sort'], '-');
            $dir = str_starts_with($spec['default_sort'], '-') ? 'desc' : 'asc';
            $by = $spec['sorts'][$key];
        }
        is_string($by) ? $query->orderBy($by, $dir) : $by($query, $dir);
        // A stable order, so pages never repeat or skip rows.
        $query->orderBy($query->getModel()->getQualifiedKeyName(), $dir);
    }

    /**
     * @param  Builder<Model>|EloquentBuilder  $query
     * @param  array{name: string, columns: array<string, Closure>}  $csv
     */
    private static function csv($query, array $csv): StreamedResponse
    {
        $name = Str::slug($csv['name']).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query, $csv) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel reads UTF-8
            fputcsv($out, array_keys($csv['columns']), escape: '');
            foreach ($query->lazy(500) as $model) {
                fputcsv($out, array_map(function (Closure $get) use ($model) {
                    $v = $get($model);

                    // Text starting with = + - @ is shown as text, never run as a formula.
                    return is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
                }, array_values($csv['columns'])), escape: '');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
