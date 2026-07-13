<?php

namespace App\Services;

use App\Models\DailyTimeSummary;
use App\Models\DailyWorktimeEvaluation;
use App\Models\User;
use Carbon\Carbon;

class ComplianceCheckService
{
    public function evaluate(User $user, Carbon|string $date): array
    {
        $date = Carbon::parse($date)->toDateString();

        $evaluation = DailyWorktimeEvaluation::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $date)
            ->first();

        if ($evaluation !== null) {
            return is_array($evaluation->flags) ? $evaluation->flags : [];
        }

        $summary = DailyTimeSummary::query()
            ->where('user_id', $user->id)
            ->where('work_date', $date)
            ->first();

        if (! $summary) {
            return [];
        }

        return is_array($summary->violation_flags) ? $summary->violation_flags : [];
    }
}
