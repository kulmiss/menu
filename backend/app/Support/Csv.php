<?php

namespace App\Support;

/**
 * Safe CSV cells. A spreadsheet runs text starting with = + - @ (or a tab or
 * carriage return) as a formula, so a dish or restaurant name like
 * =HYPERLINK("http://…") could act when the file is opened. Such text gets a
 * leading apostrophe, which spreadsheets show as plain text.
 */
final class Csv
{
    public static function cell(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /** Undoes cell() for text read back from one of our own exports. */
    public static function uncell(string $value): string
    {
        return preg_match("/^'[=+\\-@\t\r]/", $value) ? substr($value, 1) : $value;
    }
}
