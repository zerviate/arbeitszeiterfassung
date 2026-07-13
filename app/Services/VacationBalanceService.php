<?php

namespace App\Services;

use App\Models\AbsenceRecord;
use App\Models\AbsenceRequest;
use App\Models\User;
use App\Models\VacationBalance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VacationBalanceService
{
    public function __construct(
        private readonly VacationEntitlementService $vacationEntitlementService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getYearSummary(User $user, int $year): array
    {
        [$yearStart, $yearEnd] = $this->resolveYearBounds($year);

        $balance = $this->vacationEntitlementService->getOrNull($user, $year);
        $availableDays = $balance !== null
            ? $this->vacationEntitlementService->totalAvailableDays($balance)
            : 0.0;

        $pendingDays = $this->countRequestDaysByStatus($user, $yearStart, $yearEnd, [
            AbsenceRequest::STATUS_PENDING,
        ]);

        $approvedDays = $this->countRequestDaysByStatus($user, $yearStart, $yearEnd, [
            AbsenceRequest::STATUS_APPROVED,
        ]);

        $usedDays = (float) AbsenceRecord::query()
            ->where('user_id', $user->id)
            ->where('type', AbsenceRecord::TYPE_VACATION)
            ->whereDate('absence_date', '>=', $yearStart)
            ->whereDate('absence_date', '<=', $yearEnd)
            ->count();

        $remainingDays = round($availableDays - $usedDays, 2);

        return [
            'balance' => $balance,
            'year' => $year,
            'annual_entitlement_days' => (float) ($balance?->annual_entitlement_days ?? 0),
            'carryover_days' => (float) ($balance?->carryover_days ?? 0),
            'manual_adjustment_days' => (float) ($balance?->manual_adjustment_days ?? 0),
            'available_days' => $availableDays,
            'pending_days' => $pendingDays,
            'approved_days' => $approvedDays,
            'used_days' => $usedDays,
            'remaining_days' => $remainingDays,
            'remaining_after_pending_days' => round($remainingDays - $pendingDays, 2),
        ];
    }

    public function ensureSufficientBalanceForRange(User $user, Carbon|string $startDate, Carbon|string $endDate): void
    {
        foreach ($this->splitRangeDaysByYear($startDate, $endDate) as $year => $requestedDays) {
            $this->ensureSufficientBalance($user, (int) $year, $requestedDays);
        }
    }

    public function ensureSufficientBalance(User $user, int $year, float $requestedDays): void
    {
        if ($requestedDays <= 0) {
            return;
        }

        $summary = $this->getYearSummary($user, $year);

        if ($summary['available_days'] <= 0) {
            throw ValidationException::withMessages([
                'vacation_balance' => "Fuer {$year} ist kein Urlaubskonto mit Anspruch hinterlegt.",
            ]);
        }

        if ($summary['remaining_days'] < $requestedDays) {
            throw ValidationException::withMessages([
                'vacation_balance' => "Nicht genug Resturlaub verfuegbar (Jahr {$year}).",
            ]);
        }
    }

    public function createBalance(User $actor, array $payload): VacationBalance
    {
        return DB::transaction(function () use ($actor, $payload): VacationBalance {
            $this->assertUniqueForUserAndYear(
                userId: (int) $payload['user_id'],
                year: (int) $payload['year'],
                ignoreBalanceId: null,
            );

            $balance = VacationBalance::query()->create([
                'user_id' => (int) $payload['user_id'],
                'year' => (int) $payload['year'],
                'annual_entitlement_days' => (float) $payload['annual_entitlement_days'],
                'carryover_days' => (float) ($payload['carryover_days'] ?? 0),
                'manual_adjustment_days' => (float) ($payload['manual_adjustment_days'] ?? 0),
                'note' => $payload['note'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->auditLogService->log(
                actor: $actor,
                event: 'vacation_balance.created',
                auditable: $balance,
                newValues: $this->snapshot($balance),
                description: 'Urlaubskonto wurde angelegt.',
            );

            return $balance;
        });
    }

    public function updateBalance(User $actor, VacationBalance $balance, array $payload): VacationBalance
    {
        return DB::transaction(function () use ($actor, $balance, $payload): VacationBalance {
            $lockedBalance = VacationBalance::query()
                ->whereKey($balance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $this->snapshot($lockedBalance);

            $lockedBalance->forceFill([
                'annual_entitlement_days' => (float) $payload['annual_entitlement_days'],
                'carryover_days' => (float) ($payload['carryover_days'] ?? 0),
                'manual_adjustment_days' => (float) ($payload['manual_adjustment_days'] ?? 0),
                'note' => $payload['note'] ?? null,
            ])->save();

            $this->auditLogService->log(
                actor: $actor,
                event: 'vacation_balance.updated',
                auditable: $lockedBalance,
                oldValues: $before,
                newValues: $this->snapshot($lockedBalance),
                description: 'Urlaubskonto wurde aktualisiert.',
            );

            return $lockedBalance->fresh();
        });
    }

    private function countRequestDaysByStatus(User $user, string $yearStart, string $yearEnd, array $statuses): float
    {
        $requests = AbsenceRequest::query()
            ->where('user_id', $user->id)
            ->where('type', AbsenceRequest::TYPE_VACATION)
            ->whereIn('status', $statuses)
            ->whereDate('start_date', '<=', $yearEnd)
            ->whereDate('end_date', '>=', $yearStart)
            ->get(['start_date', 'end_date']);

        $days = 0.0;

        foreach ($requests as $absenceRequest) {
            $days += $this->countOverlappingDays(
                rangeStart: $absenceRequest->start_date?->toDateString() ?? $yearStart,
                rangeEnd: $absenceRequest->end_date?->toDateString() ?? $yearEnd,
                windowStart: $yearStart,
                windowEnd: $yearEnd,
            );
        }

        return round($days, 2);
    }

    private function splitRangeDaysByYear(Carbon|string $startDate, Carbon|string $endDate): array
    {
        $periodStart = Carbon::parse($startDate)->startOfDay();
        $periodEnd = Carbon::parse($endDate)->startOfDay();

        if ($periodEnd->lessThan($periodStart)) {
            [$periodStart, $periodEnd] = [$periodEnd, $periodStart];
        }

        $daysByYear = [];

        foreach (CarbonPeriod::create($periodStart, $periodEnd) as $date) {
            $year = (int) $date->format('Y');
            $daysByYear[$year] = ($daysByYear[$year] ?? 0) + 1;
        }

        return $daysByYear;
    }

    private function countOverlappingDays(string $rangeStart, string $rangeEnd, string $windowStart, string $windowEnd): float
    {
        $effectiveStart = Carbon::parse($rangeStart)->max(Carbon::parse($windowStart));
        $effectiveEnd = Carbon::parse($rangeEnd)->min(Carbon::parse($windowEnd));

        if ($effectiveEnd->lessThan($effectiveStart)) {
            return 0.0;
        }

        return (float) ($effectiveStart->diffInDays($effectiveEnd) + 1);
    }

    private function resolveYearBounds(int $year): array
    {
        $start = Carbon::create($year, 1, 1, 0, 0, 0, 'UTC');
        $end = $start->copy()->endOfYear();

        return [$start->toDateString(), $end->toDateString()];
    }

    private function assertUniqueForUserAndYear(int $userId, int $year, ?int $ignoreBalanceId): void
    {
        $exists = VacationBalance::query()
            ->where('user_id', $userId)
            ->where('year', $year)
            ->when($ignoreBalanceId !== null, fn ($query) => $query->whereKeyNot($ignoreBalanceId))
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'year' => 'Fuer diesen Benutzer und dieses Jahr existiert bereits ein Urlaubskonto.',
            ]);
        }
    }

    private function snapshot(VacationBalance $balance): array
    {
        return [
            'id' => $balance->id,
            'user_id' => $balance->user_id,
            'year' => $balance->year,
            'annual_entitlement_days' => (float) $balance->annual_entitlement_days,
            'carryover_days' => (float) $balance->carryover_days,
            'manual_adjustment_days' => (float) $balance->manual_adjustment_days,
            'note' => $balance->note,
            'created_by' => $balance->created_by,
        ];
    }
}
