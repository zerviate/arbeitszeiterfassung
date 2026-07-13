<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VacationRequestService
{
    public function __construct(
        private readonly VacationConflictService $conflictService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function createRequest(
        User $employee,
        User $requestedBy,
        Carbon|string $startDate,
        Carbon|string $endDate,
        ?string $reason = null,
    ): AbsenceRequest {
        $startDate = Carbon::parse($startDate)->toDateString();
        $endDate = Carbon::parse($endDate)->toDateString();

        if (Carbon::parse($endDate)->lessThan(Carbon::parse($startDate))) {
            throw ValidationException::withMessages([
                'end_date' => 'Enddatum darf nicht vor dem Startdatum liegen.',
            ]);
        }

        return DB::transaction(function () use ($employee, $requestedBy, $startDate, $endDate, $reason): AbsenceRequest {
            User::query()
                ->whereKey($employee->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->conflictService->hasConflictingVacationRequest($employee, $startDate, $endDate)) {
                throw ValidationException::withMessages([
                    'vacation' => 'Es existiert bereits ein ueberlappender Urlaubsantrag.',
                ]);
            }

            if ($this->conflictService->hasConflictingAbsenceRecord($employee, $startDate, $endDate)) {
                throw ValidationException::withMessages([
                    'vacation' => 'Fuer diesen Zeitraum sind bereits Abwesenheitstage erfasst.',
                ]);
            }

            $absenceRequest = AbsenceRequest::query()->create([
                'user_id' => $employee->id,
                'type' => AbsenceRequest::TYPE_VACATION,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_requested' => $this->conflictService->countDays($startDate, $endDate),
                'reason' => $reason,
                'status' => AbsenceRequest::STATUS_PENDING,
                'requested_by' => $requestedBy->id,
            ]);

            $this->auditLogService->log(
                actor: $requestedBy,
                event: 'vacation_request.created',
                auditable: $absenceRequest,
                newValues: $this->snapshot($absenceRequest),
                description: 'Urlaubsantrag wurde erstellt.',
            );

            return $absenceRequest;
        });
    }

    public function cancelRequest(AbsenceRequest $absenceRequest, User $actor): AbsenceRequest
    {
        return DB::transaction(function () use ($absenceRequest, $actor): AbsenceRequest {
            $absenceRequest = AbsenceRequest::query()
                ->whereKey($absenceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($absenceRequest->status !== AbsenceRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'vacation' => 'Nur offene Urlaubsantraege koennen storniert werden.',
                ]);
            }

            $before = $this->snapshot($absenceRequest);

            $absenceRequest->forceFill([
                'status' => AbsenceRequest::STATUS_CANCELLED,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now('UTC'),
            ])->save();

            $this->auditLogService->log(
                actor: $actor,
                event: 'vacation_request.cancelled',
                auditable: $absenceRequest,
                oldValues: $before,
                newValues: $this->snapshot($absenceRequest),
                description: 'Urlaubsantrag wurde storniert.',
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
