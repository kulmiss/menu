<?php

namespace App\Console\Commands;

use App\Support\Landing\Landing2Texts;
use App\Support\PlatformSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

/**
 * Landing 2's Amharic texts for a native speaker's review (owner, 2026-11-22).
 *
 *   php artisan landing2:amharic --export=landing2-amharic-review.csv
 *   php artisan landing2:amharic --import=reviewed.csv --dry-run   (checks every row, writes nothing)
 *   php artisan landing2:amharic --import=reviewed.csv              (saves only if every row is right)
 *
 * Columns: key | English | Amharic | max length | notes. Imported texts are saved like the
 * Super Admin's own changes (Super Admin → Landing 2 shows them), never into the code.
 */
class Landing2Amharic extends Command
{
    protected $signature = 'landing2:amharic {--export= : Write the review CSV to this file} {--import= : Read a reviewed CSV} {--dry-run : With --import: check only, save nothing}';

    protected $description = 'Export or import Landing 2\'s Amharic texts (review CSV)';

    public const HEADER = ['key', 'English', 'Amharic', 'max length', 'notes'];

    /** Texts that rightly have no Ethiopic letters (a language name, a price). */
    private const NO_ETHIOPIC = ['landing2.lang.en', 'landing2.price_demo.old', 'landing2.price_demo.new', 'footer.copyright',
        'footer.socials.facebook', 'footer.socials.instagram', 'footer.socials.tiktok', 'footer.socials.youtube', 'footer.socials.telegram', 'footer.socials.x', 'footer.socials.linkedin'];

    public function handle(): int
    {
        if (is_string($this->option('export')) && $this->option('export') !== '') {
            return $this->export((string) $this->option('export'));
        }
        if (is_string($this->option('import')) && $this->option('import') !== '') {
            return $this->import((string) $this->option('import'), (bool) $this->option('dry-run'));
        }
        $this->error('Use --export=file.csv or --import=file.csv [--dry-run].');

        return self::FAILURE;
    }

    /** @return array<string, array{en: string, am: string, max: int, notes: string}> every row, in page order */
    public static function rows(): array
    {
        $en = Landing2Texts::all('en');
        $am = Landing2Texts::all('am');
        $rows = [];
        foreach ($en as $key => $text) {
            $rows["landing2.{$key}"] = ['en' => $text, 'am' => $am[$key] ?? '', 'max' => Landing2Texts::max($key), 'notes' => self::notes($text, Landing2Texts::isShared($key) ? 'Same text as Landing 1 in English.' : '')];
        }
        Landing2Texts::applyLines();
        foreach (Landing2Texts::lines() as $key => [$english, $amharic]) {
            $rows[$key] = ['en' => $english, 'am' => $amharic, 'max' => str_starts_with($key, 'footer.') ? 120 : 160, 'notes' => self::notes($english, str_starts_with($key, 'footer.') ? 'Website footer.' : 'Plan line under the prices.')];
        }

        return $rows;
    }

    private static function notes(string $english, string $extra): string
    {
        $notes = ['Needs native review.'];
        if (preg_match_all('/:[a-z]+/', $english, $m) > 0) {
            $notes[] = 'Keep these as they are (the page fills them in): '.implode(' ', array_unique($m[0])).'.';
        }
        if (str_contains($english, '|')) {
            $notes[] = 'Count forms: keep the {1} / [2,*] parts and the | between them.';
        }
        if ($extra !== '') {
            $notes[] = $extra;
        }

        return implode(' ', $notes);
    }

    private function export(string $path): int
    {
        $out = fopen($path, 'w');
        if ($out === false) {
            $this->error("Cannot write {$path}.");

            return self::FAILURE;
        }
        fwrite($out, "\u{FEFF}"); // so spreadsheet apps read UTF-8 (Ethiopic letters)
        fputcsv($out, self::HEADER, ',', '"', '');
        foreach (self::rows() as $key => $r) {
            fputcsv($out, [$key, $r['en'], $r['am'], (string) $r['max'], $r['notes']], ',', '"', '');
        }
        fclose($out);
        $this->info('Wrote '.count(self::rows())." rows to {$path}.");

        return self::SUCCESS;
    }

    private function import(string $path, bool $dryRun): int
    {
        if (! is_file($path) || ($in = fopen($path, 'r')) === false) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }
        $rows = self::rows();
        $errors = [];
        $seen = [];
        $texts = [];
        $lines = [];
        $line = 0;
        while (($cells = fgetcsv($in, null, ',', '"', '')) !== false) {
            $line++;
            if ($line === 1) {
                $cells[0] = preg_replace('/^\x{FEFF}/u', '', (string) ($cells[0] ?? ''));
                if (array_map('trim', array_slice($cells, 0, 5)) !== self::HEADER) {
                    $errors[] = 'Row 1: the header must be: '.implode(' | ', self::HEADER).'.';
                    break;
                }

                continue;
            }
            if ($cells === [null] || implode('', array_map('strval', $cells)) === '') {
                continue;
            }
            [$key, , $amharic] = array_pad(array_map(fn ($c) => (string) $c, $cells), 5, '');
            $key = trim($key);
            $amharic = trim($amharic);
            $where = "Row {$line} ({$key})";
            if (! isset($rows[$key])) {
                $errors[] = "{$where}: unknown key.";

                continue;
            }
            if (isset($seen[$key])) {
                $errors[] = "{$where}: the key is twice in the file (also row {$seen[$key]}).";

                continue;
            }
            $seen[$key] = $line;
            foreach (self::check($key, $rows[$key]['en'], $amharic, $rows[$key]['max']) as $problem) {
                $errors[] = "{$where}: {$problem}";
            }
            if (str_starts_with($key, 'landing2.')) {
                $texts[substr($key, 9)] = $amharic;
            } else {
                $lines[$key] = $amharic;
            }
        }
        fclose($in);
        foreach (array_diff_key($rows, $seen) as $key => $r) {
            $errors[] = "Missing row: {$key}.";
        }
        if ($errors !== []) {
            foreach ($errors as $e) {
                $this->error($e);
            }
            $this->error(count($errors).' problem(s): nothing was saved.');

            return self::FAILURE;
        }
        if ($dryRun) {
            $this->info('All '.count($seen).' rows are right. Dry run: nothing was saved.');

            return self::SUCCESS;
        }
        $saved = PlatformSettings::get('landing2_texts');
        $saved = is_array($saved) ? $saved : [];
        $file = array_filter(Arr::dot((array) trans('landing2', [], 'am')), 'is_string');
        // Saved like the Super Admin's own changes: only the texts that differ from the file.
        $am = array_filter($texts, fn (string $text, string $key) => ($file[$key] ?? null) !== $text, ARRAY_FILTER_USE_BOTH);
        $fileLines = [];
        foreach (Landing2Texts::LINE_GROUPS as $group => $part) {
            $prefix = $part === null ? $group : "{$group}.{$part}";
            foreach (Arr::dot((array) trans($prefix, [], 'am')) as $k => $v) {
                $fileLines["{$prefix}.{$k}"] = $v;
            }
        }
        PlatformSettings::save([
            'landing2_texts' => ['en' => is_array($saved['en'] ?? null) ? $saved['en'] : [], 'am' => $am],
            'landing2_lines' => ['am' => array_filter($lines, fn (string $text, string $key) => ($fileLines[$key] ?? null) !== $text, ARRAY_FILTER_USE_BOTH)],
        ]);
        $this->info('Saved '.count($seen).' Amharic rows ('.count($am).' page texts and '.count(array_filter($lines, fn ($t, $k) => ($fileLines[$k] ?? null) !== $t, ARRAY_FILTER_USE_BOTH)).' other lines differ from the built-in draft).');

        return self::SUCCESS;
    }

    /**
     * Everything that must be right in one Amharic text.
     *
     * @return list<string>
     */
    public static function check(string $key, string $english, string $amharic, int $max): array
    {
        $problems = [];
        if ($amharic === '') {
            return ['the Amharic text is empty.'];
        }
        if (mb_strlen($amharic) > $max) {
            $problems[] = 'longer than '.$max.' characters ('.mb_strlen($amharic).').';
        }
        if (! mb_check_encoding($amharic, 'UTF-8')) {
            $problems[] = 'not UTF-8 text.';
        }
        if (preg_match('/<[a-z\/!]/i', $amharic) === 1) {
            $problems[] = 'HTML is not allowed.';
        }
        preg_match_all('/:[a-z]+/', $english, $want);
        preg_match_all('/:[a-z]+/', $amharic, $got);
        $want = array_unique($want[0]);
        $got = array_unique($got[0]);
        sort($want);
        sort($got);
        if ($want !== $got) {
            $problems[] = 'the placeholders must be '.($want === [] ? 'none' : implode(' ', $want)).' (found '.($got === [] ? 'none' : implode(' ', $got)).').';
        }
        if (substr_count($english, '|') !== substr_count($amharic, '|')) {
            $problems[] = 'keep the same count forms ({1} … | [2,*] …) as the English.';
        }
        if (! in_array($key, self::NO_ETHIOPIC, true) && preg_match('/[\x{1200}-\x{139F}\x{2D80}-\x{2DDF}\x{AB00}-\x{AB2F}]/u', $amharic) !== 1) {
            $problems[] = 'has no Amharic letters.';
        }

        return $problems;
    }
}
