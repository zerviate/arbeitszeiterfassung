<?php

namespace App\Services;

use App\Models\AbsenceRecord;
use App\Models\AbsenceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VacationApprovalService
{
    public function __construct(
        private readonly VacationConflictService $conflictService,
        private readonly VacationBalanceService $vacationBalanceService,
        private readonly DailyWorktimeEvaluationService $dailyWorktimeEvaluationService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function approve(AbsenceRequest $absenceRequest, User $reviewer, ?string $reviewNote = null): AbsenceRequest
    {
        return DB::transaction(function () use ($absenceRequest, $reviewer, $reviewNote): AbsenceRequest {
            $absenceRequest = AbsenceRequest::query()
                ->whereKey($absenceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($absenceRequest->status !== AbsenceRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'vacation' => 'Nur offene Urlaubsantraege koennen genehmigt werden.',
                ]);
            }

            $employee = $absenceRequest->relationLoaded('user')
                ? $absenceRequest->user
                : $absenceRequest->user()->firstOrFail();

            $employee = User::query()
                ->whereKey($employee->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->conflictService->hasConflictingVacationRequest(
                $employee,
                $absenceRequest->start_date,
                $absenceRequest->end_date,
                $absenceRequest->id,
            )) {
                throw ValidationException::withMessages([
                    'vacation' => 'Der Antrag kollidiert mit einem anderen offenen oder genehmigten Urlaubsantrag.',
                ]);
            }

            if ($this->conflictService->hasConflictingAbsenceRecord($employee, $absenceRequest->start_date, $absenceRequest->end_date)) {
                throw ValidationException::withMessages([
                    'vacation' => 'Der Antrag kollidiert mit bereits vorhandenen Abwesenheitstagen.',
                ]);
            }

            $this->vacationBalanceService->ensureSufficientBalanceForRange(
                user: $employee,
                startDate: $absenceRequest->start_date,
                endDate: $absenceRequest->end_date,
            );

            $before = $this->snapshot($absenceRequest);

            $absenceRequest->forceFill([
                'status' => AbsenceRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now('UTC'),
                'review_note' => $reviewNote,
                'cancelled_by' => null,
                'cancelled_at' => null,
            ])->save();

            $createdRecordIds = [];
            $affectedDates = $this->conflictService->expandDates($absenceRequest->start_date, $absenceRequest->end_date);

            foreach ($affectedDates as $date) {
                $record = AbsenceRecord::query()->create([
                    'user_id' => $absenceRequest->user_id,
                    'type' => AbsenceRecord::TYPE_VACATION,
                    'absence_date' => $date,
                    'source' => AbsenceRecord::SOURCE_REQUEST_APPROVED,
                    'absence_request_id' => $absenceRequest->id,
                    'recorded_by' => $reviewer->id,
                    'meta' => [
                        'approved_from_request' => true,
                    ],
                ]);

                $createdRecordIds[] = $record->id;

                $this->auditLogService->log(
                    actor: $reviewer,
                    event: 'vacation_record.created',
                    auditable: $record,
                    newValues: [
                        'id' => $record->id,
                        'user_id' => $record->user_id,
                        'absence_date' => $record->absence_date?->toDateString(),
                        'type' => $record->type,
                        'source' => $record->source,
                        'absence_request_id' => $record->absence_request_id,
                        'recorded_by' => $record->recorded_by,
                    ],
                    description: 'Urlaubstag wurde aus genehmigtem Antrag erzeugt.',
                );
            }

            foreach ($affectedDates as $date) {
                $this->dailyWorktimeEvaluationService->rebuildForUserAndDate($employee, $date);
            }

            $this->auditLogService->log(
                actor: $reviewer,
                event: 'vacation_request.approved',
                auditable: $absenceRequest,
                oldValues: $before,
                newValues: array_merge($this->snapshot($absenceRequest), [
                    'created_record_ids' => $createdRecordIds,
                ]),
                description: 'Urlaubsantrag wurde genehmigt.',
            );

            return $absenceRequest->fresh();
        });
    }

    public function reject(AbsenceRequest $absenceRequest, User $reviewer, ?string $reviewNote = null): AbsenceRequest
    {
        return DB::transaction(function () use ($absenceRequest, $reviewer, $reviewNote): AbsenceRequest {
            $absenceRequest = AbsenceRequest::query()
                ->whereKey($absenceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($absenceRequest->status !== AbsenceRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'vacation' => 'Nur offene Urlaubsantraege koennen abgelehnt werden.',
                ]);
            }

            $before = $this->snapshot($absenceRequest);

            $absenceRequest->forceFill([
                'status' => AbsenceRequest::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now('UTC'),
                'review_note' => $reviewNote,
                'cancelled_by' => null,
                'cancelled_at' => null,
            ])->save();

            $this->auditLogService->log(
                actor: $reviewer,
                event: 'vacation_request.rejected',
                auditable: $absenceRequest,
                oldValues: $before,
                newValues: $this->snapshot($absenceRequest),
                description: 'Urlaubsantrag wurde abgelehnt.',
            );

            return $absenceRequest->fresh();
        });
    }

    private function snapshot(AbsenceRequest $absenceRequest): array
    {
        return [
            'id' => $absenceRequest->id,
            'user_id' => $absenceRequest->user_id,
            'type' => $absenceRequest->type,
            'start_date' => $absenceRequest->start_date?->toDateString(),
            'end_date' => $absenceRequest->end_date?->toDateString(),
            'days_requested' => $absenceRequest->days_requested,
            'status' => $absenceRequest->status,
            'reason' => $absenceRequest->reason,
            'requested_by' => $absenceRequest->requested_by,
            'reviewed_by' => $absenceRequest->reviewed_by,
            'reviewed_at' => $absenceRequest->reviewed_at?->toIso8601String(),
            'review_note' => $absenceRequest->review_note,
            'cancelled_by' => $absenceRequest->cancelled_by,
            'cancelled_at' => $absenceRequest->cancelled_at?->toIso8601String(),
        ];
    }
}
