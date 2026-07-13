<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AuditExportWebController;
use App\Http\Controllers\AuditLogWebController;
use App\Http\Controllers\ComplianceExportWebController;
use App\Http\Controllers\ContractWebController;
use App\Http\Controllers\DailySummaryFinalizeController;
use App\Http\Controllers\HolidayCalendarWebController;
use App\Http\Controllers\ManagementTimeController;
use App\Http\Controllers\SickLeaveExportWebController;
use App\Http\Controllers\SickLeaveWebController;
use App\Http\Controllers\TimeCorrectionWebController;
use App\Http\Controllers\TimeExportWebController;
use App\Http\Controllers\TimeOverviewWebController;
use App\Http\Controllers\TimeTrackingWebController;
use App\Http\Controllers\VacationBalanceWebController;
use App\Http\Controllers\VacationRequestWebController;
use App\Http\Controllers\WorktimeEvaluationWebController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect()->route('time.index'))->name('home');

    Route::prefix('time')->name('time.')->group(function (): void {
        Route::get('/', [TimeOverviewWebController::class, 'index'])->name('index');
        Route::get('day/{date}', [TimeOverviewWebController::class, 'day'])->name('day');
        Route::get('month/{month?}', [TimeOverviewWebController::class, 'month'])->name('month');

        Route::post('clock-in', [TimeTrackingWebController::class, 'clockIn'])->name('clock-in');
        Route::post('clock-out', [TimeTrackingWebController::class, 'clockOut'])->name('clock-out');
        Route::post('break-start', [TimeTrackingWebController::class, 'breakStart'])->name('break-start');
        Route::post('break-end', [TimeTrackingWebController::class, 'breakEnd'])->name('break-end');

        Route::prefix('corrections')->name('corrections.')->group(function (): void {
            Route::get('/', [TimeCorrectionWebController::class, 'index'])->name('index');
            Route::get('create', [TimeCorrectionWebController::class, 'create'])->name('create');
            Route::post('/', [TimeCorrectionWebController::class, 'store'])->name('store');
            Route::get('{correction}', [TimeCorrectionWebController::class, 'show'])->name('show');
            Route::post('{correction}/approve', [TimeCorrectionWebController::class, 'approve'])->name('approve');
            Route::post('{correction}/reject', [TimeCorrectionWebController::class, 'reject'])->name('reject');
        });

        Route::post('summaries/{summary}/finalize', [DailySummaryFinalizeController::class, 'store'])
            ->name('summaries.finalize');
    });

    Route::prefix('management')->name('management.')->group(function (): void {
        Route::get('time', [ManagementTimeController::class, 'index'])->name('time.index');
        Route::get('time/{user}/{date}', [ManagementTimeController::class, 'show'])->name('time.show');
    });

    Route::prefix('contracts')->name('contracts.')->group(function (): void {
        Route::get('/', [ContractWebController::class, 'index'])->name('index');
        Route::get('create', [ContractWebController::class, 'create'])->name('create');
        Route::post('/', [ContractWebController::class, 'store'])->name('store');
        Route::get('{contract}/edit', [ContractWebController::class, 'edit'])->name('edit');
        Route::put('{contract}', [ContractWebController::class, 'update'])->name('update');
    });

    Route::prefix('holidays')->name('holidays.')->group(function (): void {
        Route::get('/', [HolidayCalendarWebController::class, 'index'])->name('index');
        Route::get('create', [HolidayCalendarWebController::class, 'create'])->name('create');
        Route::post('/', [HolidayCalendarWebController::class, 'store'])->name('store');
        Route::get('{holiday}/edit', [HolidayCalendarWebController::class, 'edit'])->name('edit');
        Route::put('{holiday}', [HolidayCalendarWebController::class, 'update'])->name('update');
        Route::post('{holiday}/toggle', [HolidayCalendarWebController::class, 'toggle'])->name('toggle');
        Route::delete('{holiday}', [HolidayCalendarWebController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('evaluations')->name('evaluations.')->group(function (): void {
        Route::get('users/{user}/day/{date}', [WorktimeEvaluationWebController::class, 'day'])->name('day');
        Route::get('users/{user}/week/{date}', [WorktimeEvaluationWebController::class, 'week'])->name('week');
        Route::get('users/{user}/month/{month}', [WorktimeEvaluationWebController::class, 'month'])->name('month');
    });

    Route::prefix('exports')->name('exports.')->group(function (): void {
        Route::get('time/month/csv', [TimeExportWebController::class, 'monthCsv'])->name('time.month.csv');
        Route::get('time/month/excel', [TimeExportWebController::class, 'monthExcel'])->name('time.month.excel');
        Route::get('time/day/csv', [TimeExportWebController::class, 'dayCsv'])->name('time.day.csv');
        Route::get('time/day/excel', [TimeExportWebController::class, 'dayExcel'])->name('time.day.excel');

        Route::get('compliance/day/csv', [ComplianceExportWebController::class, 'dayCsv'])->name('compliance.day.csv');
        Route::get('compliance/day/excel', [ComplianceExportWebController::class, 'dayExcel'])->name('compliance.day.excel');
        Route::get('audit/csv', [AuditExportWebController::class, 'csv'])->name('audit.csv');
        Route::get('audit/excel', [AuditExportWebController::class, 'excel'])->name('audit.excel');
        Route::get('compliance/week/csv', [ComplianceExportWebController::class, 'weekCsv'])->name('compliance.week.csv');
        Route::get('compliance/week/excel', [ComplianceExportWebController::class, 'weekExcel'])->name('compliance.week.excel');
        Route::get('compliance/month/csv', [ComplianceExportWebController::class, 'monthCsv'])->name('compliance.month.csv');
        Route::get('compliance/month/excel', [ComplianceExportWebController::class, 'monthExcel'])->name('compliance.month.excel');

        Route::get('sick-leaves/month/csv', [SickLeaveExportWebController::class, 'monthCsv'])->name('sick-leaves.month.csv');
        Route::get('sick-leaves/month/excel', [SickLeaveExportWebController::class, 'monthExcel'])->name('sick-leaves.month.excel');
    });

    Route::get('audit-logs', [AuditLogWebController::class, 'index'])->name('audit.index');

    Route::prefix('vacations')->name('vacations.')->group(function (): void {
        Route::get('/', [VacationRequestWebController::class, 'index'])->name('index');
        Route::get('create', [VacationRequestWebController::class, 'create'])->name('create');
        Route::post('/', [VacationRequestWebController::class, 'store'])->name('store');
        Route::get('{vacation}', [VacationRequestWebController::class, 'show'])->name('show');
        Route::post('{vacation}/approve', [VacationRequestWebController::class, 'approve'])->name('approve');
        Route::post('{vacation}/reject', [VacationRequestWebController::class, 'reject'])->name('reject');
        Route::post('{vacation}/cancel', [VacationRequestWebController::class, 'cancel'])->name('cancel');
    });

    Route::prefix('vacation-balances')->name('vacation-balances.')->group(function (): void {
        Route::get('/', [VacationBalanceWebController::class, 'index'])->name('index');
        Route::get('create', [VacationBalanceWebController::class, 'create'])->name('create');
        Route::post('/', [VacationBalanceWebController::class, 'store'])->name('store');
        Route::get('{vacationBalance}/edit', [VacationBalanceWebController::class, 'edit'])->name('edit');
        Route::put('{vacationBalance}', [VacationBalanceWebController::class, 'update'])->name('update');
    });

    Route::prefix('sick-leaves')->name('sick-leaves.')->group(function (): void {
        Route::get('/', [SickLeaveWebController::class, 'index'])->name('index');
        Route::get('create', [SickLeaveWebController::class, 'create'])->name('create');
        Route::post('/', [SickLeaveWebController::class, 'store'])->name('store');
        Route::get('{sickLeaveGroup}', [SickLeaveWebController::class, 'show'])->name('show');
        Route::get('{sickLeaveGroup}/edit', [SickLeaveWebController::class, 'edit'])->name('edit');
        Route::put('{sickLeaveGroup}', [SickLeaveWebController::class, 'update'])->name('update');
        Route::delete('{sickLeaveGroup}', [SickLeaveWebController::class, 'destroy'])->name('destroy');
    });
});
