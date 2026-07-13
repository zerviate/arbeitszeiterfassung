<?php

namespace App\Services;

use App\Models\AbsenceRecord;
use App\Support\SpreadsheetValueSanitizer;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class SickLeaveExportService
{
    public function getMonthExportRows(string $month): array
    {
        [$monthStart, $monthEnd] = $this->resolveMonthRange($month);

        $records = AbsenceRecord::query()
            ->with(['user', 'recordedBy', 'sickLeaveGroup'])
            ->where('type', AbsenceRecord::TYPE_SICK_LEAVE)
            ->whereDate('absence_date', '>=', $monthStart)
            ->whereDate('absence_date', '<=', $monthEnd)
            ->orderBy('user_id')
            ->orderBy('absence_date')
            ->get();

        $groupedRecords = $records->groupBy(function (AbsenceRecord $record): string {
            if ($record->sick_leave_group_id !== null) {
                return 'group-'.$record->sick_leave_group_id;
            }

            return 'single-'.$record->id;
        });

        return $groupedRecords->map(function ($group): array {
            $sorted = $group->sortBy('absence_date')->values();
            $firstRecord = $sorted->first();
            $lastRecord = $sorted->last();
            $groupModel = $firstRecord?->sickLeaveGroup;

            return SpreadsheetValueSanitizer::sanitizeRow([
                'Gruppenschluessel' => $groupModel?->group_key ?? $firstRecord?->reference_group ?? 'single-'.$firstRecord?->id,
                'Mitarbeiter' => $firstRecord?->user?->name,
                'Personalnummer' => $firstRecord?->user?->employee_number,
                'Von' => $groupModel?->start_date?->toDateString() ?? $firstRecord?->absence_date?->toDateString(),
                'Bis' => $groupModel?->end_date?->toDateString() ?? $lastRecord?->absence_date?->toDateString(),
                'Tage' => $sorted->count(),
                'Notiz' => $groupModel?->note ?? $firstRecord?->note,
                'Erfasst_von' => $groupModel?->recordedBy?->name ?? $firstRecord?->recordedBy?->name,
                'Erfasst_am' => $groupModel?->created_at?->toDateTimeString() ?? $firstRecord?->created_at?->toDateTimeString(),
            ]);
        })->values()->all();
    }

    private function resolveMonthRange(string $month): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw ValidationException::withMessages([
                'month' => 'Monat muss im Format YYYY-MM angegeben werden.',
            ]);
        }

        $start = Carbon::createFromFormat('Y-m', $month, 'UTC');

        if ($start === false || $start->format('Y-m') !== $month) {
            throw ValidationException::withMessages([
                'month' => 'Monat ist ungueltig.',
            ]);
        }

        return [
            $start->copy()->startOfMonth()->toDateString(),
            $start->copy()->endOfMonth()->toDateString(),
        ];
    }
}
