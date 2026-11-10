<?php

namespace App\Support\Import;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/**
 * The spreadsheet format for menu import/export (CSV or XLSX, QUESTIONS
 * #19, #62): one row per item, text in the restaurant's main language.
 */
class MenuSheet
{
    public const COLUMNS = [
        'menu', 'category', 'item', 'price', 'sizes', 'summary', 'description',
        'tags', 'allergens', 'visible', 'sold_out', 'calories', 'prep_minutes',
    ];

    public const REQUIRED = ['menu', 'category', 'item'];

    public const MAX_ROWS = 2000;

    /**
     * Reads a file into rows keyed by column name. Unknown columns are
     * ignored; blank rows are skipped.
     *
     * @return array<int, array<string, string>> line number → row
     */
    public function read(string $path, string $extension): array
    {
        $raw = strtolower($extension) === 'xlsx' ? $this->readXlsx($path) : $this->readCsv($path);
        if ($raw === []) {
            throw new RuntimeException('empty');
        }

        $header = array_map(fn ($h) => str_replace([' ', '-'], '_', strtolower(trim((string) $h))), array_shift($raw));
        $missing = array_diff(self::REQUIRED, $header);
        if ($missing !== []) {
            throw new RuntimeException('columns:'.implode(', ', $missing));
        }

        $rows = [];
        foreach ($raw as $i => $cells) {
            $row = [];
            foreach ($header as $col => $name) {
                if (in_array($name, self::COLUMNS, true)) {
                    $row[$name] = trim((string) ($cells[$col] ?? ''));
                }
            }
            if (implode('', $row) === '') {
                continue;
            }
            $rows[$i + 2] = $row; // line numbers as people see them (header = 1)
            if (count($rows) > self::MAX_ROWS) {
                throw new RuntimeException('too_many');
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, string|int|null>>  $rows
     */
    public function writeXlsx(string $path, array $rows): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::COLUMNS));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(fn ($c) => $row[$c] ?? '', self::COLUMNS)));
        }
        $writer->close();
    }

    /**
     * CSV with a UTF-8 marker so Excel shows Amharic and Arabic correctly.
     *
     * @param  list<array<string, string|int|null>>  $rows
     */
    public function writeCsv(string $path, array $rows): void
    {
        $out = fopen($path, 'w');
        if ($out === false) {
            throw new RuntimeException('Cannot write CSV.');
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::COLUMNS, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($c) => (string) ($row[$c] ?? ''), self::COLUMNS), ',', '"', '');
        }
        fclose($out);
    }

    /** @return list<list<mixed>> */
    private function readXlsx(string $path): array
    {
        $reader = new XlsxReader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $row->toArray());
                if (count($rows) > self::MAX_ROWS + 1) {
                    break;
                }
            }
            break; // first sheet only
        }
        $reader->close();

        return $rows;
    }

    /** @return list<list<string|null>> */
    private function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        // Excel in many countries saves with ";" instead of ",".
        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Cannot read CSV.');
        }
        fwrite($stream, $content);
        rewind($stream);
        $rows = [];
        while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $rows[] = $cells;
            if (count($rows) > self::MAX_ROWS + 1) {
                break;
            }
        }
        fclose($stream);

        return $rows;
    }
}
