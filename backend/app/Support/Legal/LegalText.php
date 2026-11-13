<?php

namespace App\Support\Legal;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Terms of Service and Privacy Policy (owner, 2026-11-13, QUESTIONS #181).
 *
 * The default texts live in resources/legal/{terms,privacy}.md (copies in
 * docs/legal). The Super Admin edits them as plain text with a tiny format,
 * which is turned into data here and escaped by the Blade view — never HTML:
 *
 *   Last updated: 13 November 2026     (first line, shown under the title)
 *   ## 1. Heading                       (section, listed in the contents)
 *   - item / "  - item" one level in    (lists)
 *   **bold**                            (inline)
 *   blank line                          (new paragraph)
 *
 * E-mail addresses and https:// links become links.
 */
final class LegalText
{
    public const PAGES = ['terms', 'privacy'];

    public static function default(string $page): string
    {
        abort_unless(in_array($page, self::PAGES, true), 404);

        return (string) file_get_contents(resource_path("legal/{$page}.md"));
    }

    /**
     * @return array{updated: string|null, blocks: list<array<string, mixed>>, toc: list<array{id: string, text: string}>}
     */
    public static function parse(string $text): array
    {
        $lines = preg_split('/\R/u', trim(str_replace("\t", '    ', $text))) ?: [];
        $updated = null;
        if ($lines !== [] && preg_match('/^last updated:\s*(.+)$/iu', trim($lines[0]), $m)) {
            $updated = trim($m[1]);
            array_shift($lines);
        }

        $blocks = [];
        $toc = [];
        /** @var list<string> $para */
        $para = [];
        /** @var array{type: string, items: list<array{text: list<array{text: string, bold: bool, href: string|null}>, children: list<mixed>}>}|null $list */
        $list = null;
        $used = [];

        foreach ([...$lines, ''] as $raw) {
            $line = rtrim($raw);
            $heading = preg_match('/^(#{2,3})\s+(.+)$/u', $line, $h) === 1;
            $item = preg_match('/^(\s*)[-*]\s+(.+)$/u', $line, $li) === 1;
            // A paragraph ends at a blank line, a heading or a list item; a list ends at anything but an item.
            if ($para !== [] && (trim($line) === '' || $heading || $item)) {
                $blocks[] = ['type' => 'p', 'lines' => array_map(self::inline(...), $para)];
                $para = [];
            }
            if ($list !== null && ! $item) {
                $blocks[] = $list;
                $list = null;
            }
            if (trim($line) === '') {
                continue;
            }
            if ($heading) {
                $text = trim($h[2]);
                $id = Str::slug(preg_replace('/^\d+\.\s*/', '', $text) ?: $text) ?: 'section';
                for ($n = 2, $base = $id; isset($used[$id]); $n++) {
                    $id = $base.'-'.$n;
                }
                $used[$id] = true;
                $level = strlen($h[1]);
                $blocks[] = ['type' => 'h'.$level, 'id' => $id, 'text' => $text];
                if ($level === 2) {
                    $toc[] = ['id' => $id, 'text' => $text];
                }
            } elseif ($item) {
                $list ??= ['type' => 'ul', 'items' => []];
                $entry = ['text' => self::inline(trim($li[2])), 'children' => []];
                $last = count($list['items']) - 1;
                if (strlen($li[1]) >= 2 && $last >= 0) {
                    $list['items'][$last]['children'][] = $entry;
                } else {
                    $list['items'][] = $entry;
                }
            } else {
                $para[] = trim($line);
            }
        }

        return ['updated' => $updated, 'blocks' => $blocks, 'toc' => $toc];
    }

    /**
     * Pieces of one line as HTML. Every piece of text is escaped; only <strong>
     * and <a href="mailto:…|https://…"> are added here, never taken from the text.
     *
     * @param  list<array{text: string, bold: bool, href: string|null}>  $pieces
     */
    public static function html(array $pieces): HtmlString
    {
        $out = '';
        foreach ($pieces as $p) {
            $text = e($p['text']);
            $text = $p['bold'] ? '<strong>'.$text.'</strong>' : $text;
            $out .= $p['href'] !== null ? '<a href="'.e($p['href']).'">'.$text.'</a>' : $text;
        }

        return new HtmlString($out);
    }

    /**
     * A line as pieces of text, each maybe bold and maybe a link.
     *
     * @return list<array{text: string, bold: bool, href: string|null}>
     */
    public static function inline(string $line): array
    {
        $out = [];
        foreach (preg_split('/(\*\*.+?\*\*)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $bold = str_starts_with($part, '**') && str_ends_with($part, '**') && strlen($part) > 4;
            $part = $bold ? substr($part, 2, -2) : $part;
            foreach (preg_split('/([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}|https:\/\/[^\s<>"]+[^\s<>".,;:!?)])/u', $part, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $piece) {
                $href = match (true) {
                    str_starts_with($piece, 'https://') => $piece,
                    (bool) preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $piece) => 'mailto:'.$piece,
                    default => null,
                };
                $out[] = ['text' => $piece, 'bold' => $bold, 'href' => $href];
            }
        }

        return $out;
    }
}
