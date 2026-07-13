<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class DateTimeFormat
{
    public static function date(CarbonInterface|string|null $value): string
    {
        $resolved = self::resolve($value);

        return $resolved?->format('d.m.Y') ?? '—';
    }

    public static function dateTime(CarbonInterface|string|null $value, ?string $timezone = null): string
    {
        $resolved = self::resolve($value);

        if ($resolved === null) {
            return '—';
        }

        if ($timezone !== null && $timezone !== '') {
            $resolved = $resolved->copy()->setTimezone($timezone);
        }

        return $resolved->format('d.m.Y H:i');
    }

    public static function monthLabel(CarbonInterface|string|null $value): string
    {
        $resolved = self::resolve($value, allowMonthString: true);

        return $resolved?->format('m.Y') ?? '—';
    }

    public static function minutes(int|float|string|null $minutes): string
    {
        if ($minutes === null || $minutes === '') {
            return '—';
        }

        if (! is_numeric($minutes)) {
            return '—';
        }

        $value = (int) round((float) $minutes);
        $sign = $value < 0 ? '-' : '';
        $value = abs($value);

        if ($value < 60) {
            return $sign . $value . 'm';
        }

        if ($value >= 1440) {
            $days = intdiv($value, 1440);
            $remaining = $value % 1440;
            $hours = intdiv($remaining, 60);
            $minutesRemaining = $remaining % 60;

            $parts = [$days . 'd'];

            if ($hours > 0) {
                $parts[] = $hours . 'h';
            }

            if ($minutesRemaining > 0) {
                $parts[] = $minutesRemaining . 'm';
            }

            return $sign . implode(' ', $parts);
        }

        $hours = intdiv($value, 60);
        $remaining = $value % 60;

        if ($remaining === 0) {
            return $sign . $hours . 'h';
        }

        return $sign . $hours . 'h ' . $remaining . 'm';
    }

    private static function resolve(CarbonInterface|string|null $value, bool $allowMonthString = false): ?CarbonInterface
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if ($allowMonthString && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $trimmed)) {
            return Carbon::createFromFormat('Y-m', $trimmed, 'UTC');
        }

        return Carbon::parse($trimmed, 'UTC');
    }
}
