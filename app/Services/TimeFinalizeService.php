<?php

namespace App\Services;

use App\Models\DailyTimeSummary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeFinalizeService
{
    public function __construct(
        private readonly DailySummaryService $dailySummaryService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function finalizeDay(User $targetUser, Carbon|string $date, User $actor): DailyTimeSummary
    {
        $date = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($targetUser, $date, $actor): DailyTimeSummary {
            $this->dailySummaryService->rebuildForUserAndDate($targetUser->id, $date);

            $summary = DailyTimeSummary::query()
                ->where('user_id', $targetUser->id)
                ->where('work_date', $date)
                ->lockForUpdate()
                ->firstOrFail();

            if ($summary->has_open_entries) {
                throw ValidationException::withMessages([
                    'finalize' => 'Tage mit offenen Eintraegen koennen nicht finalisiert werden.',
                ]);
            }

            if ($summary->finalized_at !== null) {
                throw ValidationException::withMessages([
                    'finalize' => 'Der Tag ist bereits finalisiert.',
                ]);
            }

            $summary->forceFill([
                'finalized_at' => now('UTC'),
                'finalized_by' => $actor->id,
            ])->save();

            $this->auditLogService->log(
                actor: $actor,
                event: 'daily_summary.finalized',
                auditable: $summary,
                newValues: [
                    'user_id' => $targetUser->id,
                    'work_date' => $date,
                    'finalized_by' => $actor->id,
                ],
                description: 'Arbeitstag wurde finalisiert.',
            );

            return $summary->fresh();
        });
    }

    public function unfinalizeDay(User $targetUser, Carbon|string $date, User $actor): DailyTimeSummary
    {
        $date = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($targetUser, $date, $actor): DailyTimeSummary {
            $summary = DailyTimeSummary::query()
                ->where('user_id', $targetUser->id)
                ->where('work_date', $date)
                ->lockForUpdate()
                ->first();

            if (! $summary || $summary->finalized_at === null) {
                throw ValidationException::withMessages([
                    'unfinalize' => 'Der Tag ist nicht finalisiert.',
                ]);
            }

            $oldValues = [
                'finalized_at' => $summary->finalized_at?->toIso8601String(),
                'finalized_by' => $summary->finalized_by,
            ];

            $summary->forceFill([
                'finalized_at' => null,
                'finalized_by' => null,
            ])->save();

            $this->auditLogService->log(
                actor: $actor,
                event: 'daily_summary.unfinalized',
                auditable: $summary,
                oldValues: $oldValues,
                newValues: [
                    'finalized_at' => null,
                    'finalized_by' => null,
                ],
                description: 'Arbeitstag wurde entfinalisiert.',
            );

            return $summary->fresh();
        });
    }
}
