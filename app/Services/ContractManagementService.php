<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractManagementService
{
    public function __construct(
        private readonly WorkScheduleService $workScheduleService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function create(User $actor, User $targetUser, array $payload): Contract
    {
        [$validFrom, $validTo] = $this->resolveDateRange($payload['valid_from'], $payload['valid_to'] ?? null);
        $workdaysPattern = $this->workScheduleService->normalizeWorkdaysPattern($payload['workdays_pattern'] ?? []);
        $weeklyMinutes = max(0, (int) ($payload['weekly_minutes'] ?? 0));
        $isActive = (bool) ($payload['is_active'] ?? true);

        $this->assertWorkdaysPatternIsNotEmpty($workdaysPattern);

        return DB::transaction(function () use ($actor, $targetUser, $weeklyMinutes, $workdaysPattern, $validFrom, $validTo, $isActive): Contract {
            User::query()
                ->whereKey($targetUser->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($isActive) {
                $this->assertNoOverlap($targetUser->id, $validFrom, $validTo, null);
            }

            $contract = Contract::query()->create([
                'user_id' => $targetUser->id,
                'weekly_minutes' => $weeklyMinutes,
                'workdays_pattern' => $workdaysPattern,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'is_active' => $isActive,
                'created_by' => $actor->id,
            ]);

            $this->auditLogService->log(
                actor: $actor,
                event: 'contract.created',
                auditable: $contract,
                newValues: $this->snapshot($contract),
                description: 'Vertrag wurde angelegt.',
            );

            return $contract;
        });
    }

    public function update(User $actor, Contract $contract, array $payload): Contract
    {
        [$validFrom, $validTo] = $this->resolveDateRange($payload['valid_from'], $payload['valid_to'] ?? null);
        $workdaysPattern = $this->workScheduleService->normalizeWorkdaysPattern($payload['workdays_pattern'] ?? []);
        $weeklyMinutes = max(0, (int) ($payload['weekly_minutes'] ?? 0));
        $isActive = (bool) ($payload['is_active'] ?? false);

        $this->assertWorkdaysPatternIsNotEmpty($workdaysPattern);

        return DB::transaction(function () use ($actor, $contract, $weeklyMinutes, $workdaysPattern, $validFrom, $validTo, $isActive): Contract {
            $lockedContract = Contract::query()
                ->whereKey($contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            User::query()
                ->whereKey($lockedContract->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($isActive) {
                $this->assertNoOverlap($lockedContract->user_id, $validFrom, $validTo, $lockedContract->id);
            }

            $before = $this->snapshot($lockedContract);

            $lockedContract->forceFill([
                'weekly_minutes' => $weeklyMinutes,
                'workdays_pattern' => $workdaysPattern,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'is_active' => $isActive,
            ])->save();

            $this->auditLogService->log(
                actor: $actor,
                event: 'contract.updated',
                auditable: $lockedContract,
                oldValues: $before,
                newValues: $this->snapshot($lockedContract),
                description: 'Vertrag wurde aktualisiert.',
            );

            return $lockedContract->fresh();
        });
    }

    private function assertNoOverlap(int $userId, string $validFrom, ?string $validTo, ?int $ignoreContractId): void
    {
        $rangeEnd = $validTo ?? '9999-12-31';

        $overlapExists = Contract::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->when($ignoreContractId !== null, fn ($query) => $query->whereKeyNot($ignoreContractId))
            ->whereDate('valid_from', '<=', $rangeEnd)
            ->where(function ($query) use ($validFrom): void {
                $query
                    ->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $validFrom);
            })
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'contract' => 'Der Vertragszeitraum ueberlappt mit einem bereits aktiven Vertrag.',
            ]);
        }
    }

    private function assertWorkdaysPatternIsNotEmpty(array $workdaysPattern): void
    {
        $activeDays = collect($workdaysPattern)
            ->filter(static fn (bool $active): bool => $active)
            ->count();

        if ($activeDays === 0) {
            throw ValidationException::withMessages([
                'workdays_pattern' => 'Mindestens ein Arbeitstag muss aktiv sein.',
            ]);
        }
    }

    private function resolveDateRange(string $validFrom, ?string $validTo): array
    {
        $from = Carbon::parse($validFrom)->toDateString();
        $to = $validTo !== null && trim($validTo) !== '' ? Carbon::parse($validTo)->toDateString() : null;

        if ($to !== null && Carbon::parse($to)->lessThan(Carbon::parse($from))) {
            throw ValidationException::withMessages([
                'valid_to' => 'Gueltig bis darf nicht vor gueltig von liegen.',
            ]);
        }

        return [$from, $to];
    }

    private function snapshot(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'user_id' => $contract->user_id,
            'weekly_minutes' => $contract->weekly_minutes,
            'workdays_pattern' => $contract->workdays_pattern,
            'valid_from' => $contract->valid_from?->toDateString(),
            'valid_to' => $contract->valid_to?->toDateString(),
            'is_active' => $contract->is_active,
            'created_by' => $contract->created_by,
        ];
    }
}
