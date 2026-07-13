<?php

namespace App\Support;

class SpreadsheetValueSanitizer
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@'];

    public static function sanitizeRows(array $rows): array
    {
        return array_map(
            static fn (array $row): array => self::sanitizeRow($row),
            $rows,
        );
    }

    public static function sanitizeRow(array $row): array
    {
        $sanitized = [];

        foreach ($row as $key => $value) {
            $sanitized[$key] = self::sanitizeValue($value);
        }

        return $sanitized;
    }

    public static function sanitizeValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || str_starts_with($value, "'")) {
            return $value;
        }

        $trimmed = ltrim($value, " \t\r\n");
        $firstCharacter = $trimmed !== '' ? substr($trimmed, 0, 1) : '';

        if (
            in_array($firstCharacter, self::DANGEROUS_PREFIXES, true)
            || str_starts_with($value, "\t")
            || str_starts_with($value, "\r")
            || str_starts_with($value, "\n")
        ) {
            return "'".$value;
        }

        return $value;
    }
}
