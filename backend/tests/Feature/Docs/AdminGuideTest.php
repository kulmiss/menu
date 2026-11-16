<?php

use App\Console\Commands\AdminGuideCommand;
use App\Support\Help\HelpLibrary;

/** The Super Admin guide in docs/ is written from the same pages as the panel's Guide (QUESTIONS #194). */
it('keeps docs/admin-guide the same as the Super Admin help pages', function () {
    foreach (AdminGuideCommand::files() as $name => $text) {
        $path = AdminGuideCommand::dir().'/'.$name;
        expect(is_file($path) && file_get_contents($path) === $text)
            ->toBeTrue("docs/admin-guide/$name is out of date: run `php artisan docs:admin-guide` and `node frontend/scripts/admin-guide-pdf.mjs`.");
    }
    expect(dirname(AdminGuideCommand::dir()).'/admin-guide.pdf')->toBeFile();
});

it('links only to pages and pictures that exist', function () {
    foreach (AdminGuideCommand::files() as $name => $text) {
        preg_match_all('/\]\(([^)#\s]+)\)/', $text, $m);
        foreach ($m[1] as $link) {
            if (str_starts_with($link, 'http') || str_starts_with($link, 'mailto:')) {
                continue;
            }
            expect(file_exists(AdminGuideCommand::dir().'/'.$link) || $link === '../admin-guide.pdf')->toBeTrue("$name links to a missing $link");
        }
    }
});

it('has a guide page behind the "?" of every Super Admin screen', function () {
    $layout = (string) file_get_contents(dirname(base_path()).'/frontend/src/features/platform/layout.tsx');
    preg_match('/const NAV = \[(.*?)\]/s', $layout, $nav);
    preg_match_all("/'([a-z\\-]+)'/", $nav[1], $sections);
    $pages = collect(HelpLibrary::defaults('platform'))->flatMap(fn ($a) => $a['pages'])->map(fn ($p) => explode('#', $p)[0])->unique()->all();
    foreach (['', ...$sections[1], 'security'] as $section) {
        $url = rtrim('/platform/'.$section, '/');
        expect(in_array($url, $pages, true))->toBeTrue("No Super Admin guide page has `pages: $url`");
    }
});
