<?php

declare(strict_types=1);

namespace Sunrice\Support;

/**
 * Exported CSVs carry text visitors typed (form submissions) and editors
 * entered. A cell starting with = + - @ (or a tab/return) runs as a
 * formula when the file is opened in Excel or Sheets; a leading quote
 * makes it plain text.
 */
class CsvCell
{
    public static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
