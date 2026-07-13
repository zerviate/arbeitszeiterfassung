<?php

namespace App\Services;

use App\Models\DailyTimeSummary;
use App\Models\TimeCorrection;
use App\Models\TimeEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeCorrectionService
{
    public function __construct(
        private readonly SessionRebuildService $sessionRebuildService,
        private readonly DailySummaryService $dailySummaryService,
        private readonly DailyWorktimeEvaluationService $dailyWorktimeEvaluationService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function requestCorrection(
        User $targetUser,
        User $requestedBy,
        Carbon|string $workDate,
        array $newValues,
        string $reason,
        ?array $oldValues = null,
    ): TimeCorrection {
        $workDate = Carbon::parse($workDate)->toDateString();
        $this->validateCorrectionPayload($newValues);

        return DB::transaction(function () use ($targetUser, $requestedBy, $workDate, $newValues, $reason, $oldValues): TimeCorrection {
            $this->assertDayIsMutable($targetUser->id, $workDate);

            $correction = TimeCorrection::query()->create([
                'user_id' => $targetUser->id,
                'work_date' => $workDate,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'reason' => $reason,
                'status' => TimeCorrection::STATUS_PENDING,
                'requested_by' => $requestedBy->id,
            ]);

            $this->auditLogService->log(
                actor: $requestedBy,
                event: 'time_correction.requested',
                auditable: $correction,
                newValues: [
                    'user_id' => $targetUser->id,
                    'work_date' => $workDate,
                    'reason' => $reason,
                    'new_values' => $newValues,
                ],
                description: 'Zeitkorrektur wurde beantragt.',
            );

            return $correction;
        });
    }

    public function approveCorrection(TimeCorrection $correction, User $reviewer, ?string $reviewNote = null): TimeCorrection
    {
        return DB::transaction(function () use ($correction, $reviewer, $reviewNote): TimeCorrection {
            $correction = TimeCorrection::query()->whereKey($correction->id)->lockForUpdate()->firstOrFail();

            if ($correction->status !== TimeCorrection::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Nur offene Korrekturantraege koennen freigegeben werden.',
                ]);
            }

            $workDate = $correction->work_date->toDateString();

            $this->assertDayIsMutable($correction->user_id, $workDate);

            $events = TimeEvent::query()
                ->where('user_id', $correction->user_id)
                ->where('work_date', $workDate)
                ->whereNull('invalidated_at')
                ->lockForUpdate()
                ->get();

            $previousEvents = $events->map(function (TimeEvent $event): array {
                return [
                    'id' => $event->id,
                    'type' => $event->type,
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                    'work_date' => $event->work_date?->toDateString(),
                    'source' => $event->source,
                    'reason' => $event->reason,
                    'meta' => $event->meta,
                ];
            })->values()->all();

            $invalidationTimestamp = now('UTC');

            foreach ($events as $event) {
                $event->forceFill([
                    'invalidated_at' => $invalidationTimestamp,
                    'invalidated_by' => $reviewer->id,
                    'invalidation_reason' => 'approved_correction:'.$correction->id,
                ])->save();
            }

            $createdEvents = $this->createManualCorrectionEvents($correction, $reviewer);

            $correction->forceFill([
                'old_values' => $correction->old_values ?? $previousEvents,
                'status' => TimeCorrection::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now('UTC'),
                'review_note' => $reviewNote,
            ])->save();

            $this->sessionRebuildService->rebuildForUserAndDate($correction->user_id, $workDate);
            $this->dailySummaryService->rebuildForUserAndDate($correction->user_id, $workDate);

            $targetUser = User::query()->findOrFail($correction->user_id);
            $this->dailyWorktimeEvaluationService->rebuildForUserAndDate($targetUser, $workDate);

            $this->auditLogService->log(
                actor: $reviewer,
                event: 'time_correction.approved',
                auditable: $correction,
                oldValues: ['status' => TimeCorrection::STATUS_PENDING],
                newValues: [
                    'status' => TimeCorrection::STATUS_APPROVED,
                    'review_note' => $reviewNote,
                    'created_events' => $createdEvents,
                ],
                description: 'Zeitkorrektur wurde genehmigt und angewendet.',
            );

            return $correction->fresh();
        });
    }

    public function rejectCorrection(TimeCorrection $correction, User $reviewer, ?string $reviewNote = null): TimeCorrection
    {
        return DB::transaction(function () use ($correction, $reviewer, $reviewNote): TimeCorrection {
            $correction = TimeCorrection::query()->whereKey($correction->id)->lockForUpdate()->firstOrFail();

            if ($correction->status !== TimeCorrection::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Nur offene Korrekturantraege koennen abgelehnt werden.',
                ]);
            }

            $correction->forceFill([
                'status' => TimeCorrection::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now('UTC'),
                'review_note' => $reviewNote,
            ])->save();

            $this->auditLogService->log(
                actor: $reviewer,
                event: 'time_correction.rejected',
                auditable: $correction,
                oldValues: ['status' => TimeCorrection::STATUS_PENDING],
                newValues: [
                    'status' => TimeCorrection::STATUS_REJECTED,
                    'review_note' => $reviewNote,
                ],
                description: 'Zeitkorrektur wurde abgelehnt.',
            );

            return $correction->fresh();
        });
    }

    private function validateCorrectionPayload(array $newValues): void
    {
        $events = $newValues['events'] ?? null;

        if (! is_array($events) || $events === []) {
            throw ValidationException::withMessages([
                'new_values.events' => 'Es muss mindestens ein neues Event angegeben werden.',
            ]);
        }

        foreach ($events as $index => $eventData) {
            if (! is_array($eventData)) {
                throw ValidationException::withMessages([
                    "new_values.events.$index" => 'Event-Daten muessen als Objekt uebergeben werden.',
                ]);
            }

            $type = $eventData['type'] ?? null;
            $occurredAt = $eventData['occurred_at'] ?? null;

            if (! in_array($type, [
                TimeEvent::TYPE_CLOCK_IN,
                TimeEvent::TYPE_CLOCK_OUT,
                TimeEvent::TYPE_BREAK_START,
                TimeEvent::TYPE_BREAK_END,
            ], true)) {
                throw ValidationException::withMessages([
                    "new_values.events.$index.type" => 'Event-Typ ist ungueltig.',
                ]);
            }

            if (! is_string($occurredAt)) {
                throw ValidationException::withMessages([
                    "new_values.events.$index.occurred_at" => 'occurred_at muss ein gueltiges Datum sein.',
                ]);
            }

            try {
                Carbon::parse($occurredAt);
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages([
                    "new_values.events.$index.occurred_at" => 'occurred_at muss ein gueltiges Datum sein.',
                ]);
            }
        }
    }

    private function createManualCorrectionEvents(TimeCorrection $correction, User $reviewer): array
    {
        $events = $correction->new_values['events'] ?? [];
        $createdEventIds = [];
        $workDate = $correction->work_date->toDateString();

        foreach ($events as $eventData) {
            $occurredAtUtc = Carbon::parse($eventData['occurred_at'])->utc();
            $source = $eventData['source'] ?? TimeEvent::SOURCE_ADMIN;

            if (! in_array($source, [
                TimeEvent::SOURCE_WEB,
                TimeEvent::SOURCE_MOBILE,
                TimeEvent::SOURCE_TERMINAL,
                TimeEvent::SOURCE_ADMIN,
                TimeEvent::SOURCE_IMPORT,
            ], true)) {
                $source = TimeEvent::SOURCE_ADMIN;
            }

            $event = TimeEvent::query()->create([
                'user_id' => $correction->user_id,
                'type' => TimeEvent::TYPE_MANUAL_CORRECTION,
                'occurred_at' => $occurredAtUtc,
                'work_date' => $workDate,
                'source' => $source,
                'created_by' => $reviewer->id,
                'reason' => $eventData['reason'] ?? $correction->reason,
                'meta' => array_merge(
                    is_array($eventData['meta'] ?? null) ? $eventData['meta'] : [],
                    [
                        'correction_id' => $correction->id,
                        'corrected_type' => $eventData['type'],
                        'requested_by' => $correction->requested_by,
                        'reviewed_by' => $reviewer->id,
                    ],
                ),
            ]);

            $createdEventIds[] = $event->id;
        }

        return $createdEventIds;
    }

    private function assertDayIsMutable(int $userId, string $workDate): void
    {
        $summary = DailyTimeSummary::query()
            ->where('user_id', $userId)
            ->where('work_date', $workDate)
            ->lockForUpdate()
            ->first();

        if ($summary?->finalized_at !== null) {
            throw ValidationException::withMessages([
                'work_date' => 'Der Arbeitstag ist finalisiert und kann nicht mehr veraendert werden.',
            ]);
        }
    }
}
