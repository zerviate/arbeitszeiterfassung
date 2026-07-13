<?php

namespace App\Services;

use App\Models\BreakSession;
use App\Models\DailyTimeSummary;
use App\Models\TimeEvent;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DailySummaryService
{
    public function rebuildForUserAndDate(int $userId, Carbon|string $date): DailyTimeSummary
    {
        $date = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($userId, $date): DailyTimeSummary {
            $sessions = WorkSession::query()
                ->where('user_id', $userId)
                ->whereDate('work_date', $date)
                ->get();

            $sessionIds = $sessions->pluck('id');

            $grossMinutes = (int) $sessions->sum('gross_minutes');

            $breakMinutes = 0;

            if ($sessionIds->isNotEmpty()) {
                $breakMinutes = (int) BreakSession::query()
                    ->whereIn('work_session_id', $sessionIds)
                    ->sum('minutes');
            }

            $netMinutes = max(0, $grossMinutes - $breakMinutes);
            $overtimeMinutes = max(0, $netMinutes - 480);

            $hasOpenSessions = $sessions->contains('status', WorkSession::STATUS_OPEN);
            $hasOpenBreaks = false;

            if ($sessionIds->isNotEmpty()) {
                $hasOpenBreaks = BreakSession::query()
                    ->whereIn('work_session_id', $sessionIds)
                    ->where('status', BreakSession::STATUS_OPEN)
                    ->exists();
            }

            $hasOpenEntries = $hasOpenSessions || $hasOpenBreaks;

            $hasManualCorrections = TimeEvent::query()
                ->where('user_id', $userId)
                ->whereDate('work_date', $date)
                ->where('type', TimeEvent::TYPE_MANUAL_CORRECTION)
                ->whereNull('invalidated_at')
                ->exists();

            $violationFlags = $this->buildViolationFlags(
                $netMinutes,
                $breakMinutes,
                $hasOpenEntries,
                $hasManualCorrections,
            );

            $payload = [
                'gross_minutes' => $grossMinutes,
                'break_minutes' => $breakMinutes,
                'net_minutes' => $netMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'has_open_entries' => $hasOpenEntries,
                'has_manual_corrections' => $hasManualCorrections,
                'violation_flags' => $violationFlags,
            ];

            $summary = DailyTimeSummary::query()
                ->where('user_id', $userId)
                ->whereDate('work_date', $date)
                ->first();

            if ($summary !== null) {
                $summary->forceFill($payload)->save();

                return $summary->fresh();
            }

            return DailyTimeSummary::query()->create(array_merge($payload, [
                'user_id' => $userId,
                'work_date' => $date,
            ]));
        });
    }

    private function buildViolationFlags(int $netMinutes, int $breakMinutes, bool $hasOpenEntries, bool $hasManualCorrections): array
    {
        $flags = [];

        if ($hasOpenEntries) {
            $flags[] = 'open_session';
        }

        if ($netMinutes > 360 && $breakMinutes < 30) {
            $flags[] = 'missing_break_30';
        }

        if ($netMinutes > 540 && $breakMinutes < 45) {
            $flags[] = 'missing_break_45';
        }

        if ($netMinutes > 600) {
            $flags[] = 'daily_limit_exceeded';
        }

        if ($hasManualCorrections) {
            $flags[] = 'manual_correction_present';
        }

        return array_values(array_unique($flags));
    }
}
