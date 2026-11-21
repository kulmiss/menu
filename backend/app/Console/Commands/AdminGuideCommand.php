<?php

namespace App\Console\Commands;

use App\Support\Help\HelpLibrary;
use App\Support\Help\HelpRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Writes the Super Admin guide to docs/admin-guide/ (one Markdown file per
 * page plus README.md with the contents) and docs/admin-guide/admin-guide.html
 * (everything on one page, printed to docs/admin-guide.pdf by
 * frontend/scripts/admin-guide-pdf.mjs). The source is
 * backend/resources/help/platform, the same pages as Super Admin → Guide;
 * tests/Feature/Docs/AdminGuideTest.php fails when the files are out of date.
 */
class AdminGuideCommand extends Command
{
    protected $signature = 'docs:admin-guide {--check : Exit 1 when docs/admin-guide is out of date}';

    protected $description = 'Write docs/admin-guide/ from the Super Admin help pages';

    public static function dir(): string
    {
        return dirname(base_path()).'/docs/admin-guide';
    }

    public function handle(): int
    {
        $files = self::files();
        if ($this->option('check')) {
            $stale = array_keys(array_filter($files, fn ($text, $name) => ! is_file(self::dir().'/'.$name) || file_get_contents(self::dir().'/'.$name) !== $text, ARRAY_FILTER_USE_BOTH));
            $extra = array_diff(array_map('basename', glob(self::dir().'/*.{md,html}', GLOB_BRACE) ?: []), array_keys($files));
            if ($stale !== [] || $extra !== []) {
                $this->error('docs/admin-guide is out of date: run `php artisan docs:admin-guide` (then the PDF script). '.implode(', ', [...$stale, ...$extra]));

                return self::FAILURE;
            }
            $this->info('docs/admin-guide is up to date.');

            return self::SUCCESS;
        }
        @mkdir(self::dir(), 0755, true);
        foreach (glob(self::dir().'/*.{md,html}', GLOB_BRACE) ?: [] as $old) {
            unlink($old);
        }
        foreach ($files as $name => $text) {
            file_put_contents(self::dir().'/'.$name, $text);
        }
        $this->info('Wrote '.count($files).' files to docs/admin-guide. Now: node frontend/scripts/admin-guide-pdf.mjs');

        return self::SUCCESS;
    }

    /** @return array<string, string> file name => content */
    public static function files(): array
    {
        $render = new HelpRenderer(null, null, forDocs: true);
        $topics = (array) trans('help.topics.platform', [], 'en');
        $articles = HelpLibrary::defaults('platform');
        $note = '<!-- Written by `php artisan docs:admin-guide` from backend/resources/help/platform. Edit those files, not this one. -->';
        $files = [];
        $toc = [];
        $html = [];
        $n = 0;
        foreach ($topics as $key => $title) {
            $list = array_filter($articles, fn ($a) => $a['topic'] === $key);
            if ($list === []) {
                continue;
            }
            $n++;
            $toc[] = '';
            $toc[] = "## {$n}. {$title}";
            $toc[] = '';
            $html[] = '<h1 class="topic">'.e("{$n}. {$title}").'</h1>';
            foreach ($list as $slug => $a) {
                $body = $render->markdown((string) $a['body']);
                $files["{$slug}.md"] = $note."\n\n# ".$a['title']."\n\n".($a['summary'] !== '' ? '*'.$a['summary']."*\n\n" : '').$body;
                $toc[] = "- [{$a['title']}]({$slug}.md) — {$a['summary']}";
                // One page: links jump inside it (the HTML sits next to the .md files).
                $one = preg_replace('/\]\(([a-z0-9\-]+)\.md\)/', '](#$1)', $body) ?? $body;
                $html[] = '<section id="'.e($slug).'"><h2>'.e($a['title']).'</h2>'.Str::markdown($one, ['html_input' => 'escape', 'allow_unsafe_links' => false]).'</section>';
            }
        }
        $files['README.md'] = $note."\n\n# Super Admin guide\n\nHow to set up and run the platform, in easy steps. The same pages are inside the Super Admin panel (**Guide**, and the **?** button on every page). One PDF: [admin-guide.pdf](../admin-guide.pdf).\n".implode("\n", $toc)."\n";
        $files['admin-guide.html'] = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Super Admin guide</title><style>'
            .'body{font:14px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#0f172a;max-width:720px;margin:0 auto;padding:24px}'
            .'h1.topic{page-break-before:always;font-size:24px;border-bottom:2px solid #0f172a;padding-bottom:6px}h1.cover{font-size:34px;margin-top:30vh}'
            .'h2{font-size:18px;margin-top:28px}section{page-break-inside:auto}img{max-width:100%;border:1px solid #e2e8f0;border-radius:6px;margin:8px 0}'
            .'pre{background:#f1f5f9;padding:10px;border-radius:6px;white-space:pre-wrap;font-size:12px}code{background:#f1f5f9;padding:1px 4px;border-radius:4px;font-size:12px}'
            .'table{border-collapse:collapse;width:100%}td,th{border-bottom:1px solid #e2e8f0;padding:4px 6px;text-align:left}a{color:#1d4ed8}'
            .'</style></head><body><h1 class="cover">Super Admin guide</h1><p>Setup from zero to live, daily use, problems and answers, and a cheat sheet.</p>'
            ."\n".implode("\n", $html)."\n</body></html>\n";
        ksort($files);

        return $files;
    }
}
