<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class DateInput
{
    public static function resolveDate(string $date, string $field = 'date'): string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw ValidationException::withMessages([
                $field => 'Datum muss im Format YYYY-MM-DD angegeben werden.',
            ]);
        }

        $parsed = Carbon::createFromFormat('Y-m-d', $date, 'UTC');

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw ValidationException::withMessages([
                $field => 'Datum ist ungueltig.',
            ]);
        }

        return $parsed->toDateString();
    }

    public static function resolveMonthRange(string $month, string $field = 'month'): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw ValidationException::withMessages([
                $field => 'Monat muss im Format YYYY-MM angegeben werden.',
            ]);
        }

        $start = Carbon::createFromFormat('Y-m', $month, 'UTC');

        if ($start === false || $start->format('Y-m') !== $month) {
            throw ValidationException::withMessages([
                $field => 'Monat ist ungueltig.',
            ]);
        }

        return [
            $start->copy()->startOfMonth()->toDateString(),
            $start->copy()->endOfMonth()->toDateString(),
        ];
    }
}
