<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\AbsenceRecord;
use App\Models\AbsenceRequest;
use App\Models\Contract;
use App\Models\DailyTimeSummary;
use App\Models\DailyWorktimeEvaluation;
use App\Models\HolidayCalendarEntry;
use App\Models\SickLeaveGroup;
use App\Models\TimeCorrection;
use App\Models\VacationBalance;
use App\Models\WorkSession;
use App\Policies\AbsenceRecordPolicy;
use App\Policies\AbsenceRequestPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\ContractPolicy;
use App\Policies\DailyTimeSummaryPolicy;
use App\Policies\DailyWorktimeEvaluationPolicy;
use App\Policies\HolidayCalendarEntryPolicy;
use App\Policies\SickLeaveGroupPolicy;
use App\Policies\TimeCorrectionPolicy;
use App\Policies\VacationBalancePolicy;
use App\Policies\WorkSessionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::policy(WorkSession::class, WorkSessionPolicy::class);
        Gate::policy(TimeCorrection::class, TimeCorrectionPolicy::class);
        Gate::policy(DailyTimeSummary::class, DailyTimeSummaryPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(AbsenceRequest::class, AbsenceRequestPolicy::class);
        Gate::policy(AbsenceRecord::class, AbsenceRecordPolicy::class);
        Gate::policy(SickLeaveGroup::class, SickLeaveGroupPolicy::class);
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(DailyWorktimeEvaluation::class, DailyWorktimeEvaluationPolicy::class);
        Gate::policy(HolidayCalendarEntry::class, HolidayCalendarEntryPolicy::class);
        Gate::policy(VacationBalance::class, VacationBalancePolicy::class);
    }
}
