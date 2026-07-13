<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\SickLeaveApiController;
use App\Http\Controllers\TimeCorrectionController;
use App\Http\Controllers\TimeFinalizeController;
use App\Http\Controllers\TimeOverviewController;
use App\Http\Controllers\TimeTrackingController;
use App\Http\Controllers\WorktimeEvaluationApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::prefix('time')->group(function (): void {
        Route::post('clock-in', [TimeTrackingController::class, 'clockIn']);
        Route::post('clock-out', [TimeTrackingController::class, 'clockOut']);
        Route::post('break-start', [TimeTrackingController::class, 'startBreak']);
        Route::post('break-end', [TimeTrackingController::class, 'endBreak']);

        Route::post('corrections', [TimeCorrectionController::class, 'store']);
        Route::post('corrections/{correction}/approve', [TimeCorrectionController::class, 'approve']);
        Route::post('corrections/{correction}/reject', [TimeCorrectionController::class, 'reject']);

        Route::get('day/{date}', [TimeOverviewController::class, 'day']);
        Route::get('month/{month}', [TimeOverviewController::class, 'month']);

        Route::post('day/{date}/finalize', [TimeFinalizeController::class, 'finalize']);
        Route::post('day/{date}/unfinalize', [TimeFinalizeController::class, 'unfinalize']);
    });

    Route::get('audit-logs', [AuditLogController::class, 'index']);

    Route::prefix('sick-leaves')->group(function (): void {
        Route::get('/', [SickLeaveApiController::class, 'index']);
        Route::post('/', [SickLeaveApiController::class, 'store']);
        Route::get('{sickLeaveGroup}', [SickLeaveApiController::class, 'show']);
        Route::put('{sickLeaveGroup}', [SickLeaveApiController::class, 'update']);
        Route::delete('{sickLeaveGroup}', [SickLeaveApiController::class, 'destroy']);
    });

    Route::prefix('evaluations')->group(function (): void {
        Route::get('day/{date}', [WorktimeEvaluationApiController::class, 'day']);
        Route::get('week/{date}', [WorktimeEvaluationApiController::class, 'week']);
        Route::get('month/{month}', [WorktimeEvaluationApiController::class, 'month']);
    });
});
