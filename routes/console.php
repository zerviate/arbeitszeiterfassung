<?php

use App\Models\User;
use App\Services\WorktimeEvaluationRebuildService;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('evaluations:rebuild {--from=} {--to=} {--user_id=} {--chunk=100}', function (): int {
    $fromOption = $this->option('from');
    $toOption = $this->option('to');
    $userIdOption = $this->option('user_id');
    $chunkOption = (int) $this->option('chunk');

    try {
        $from = $fromOption ? Carbon::parse((string) $fromOption)->toDateString() : now('UTC')->toDateString();
        $to = $toOption ? Carbon::parse((string) $toOption)->toDateString() : $from;
    } catch (\Throwable $exception) {
        $this->error('Ungueltige Datumsangaben. Erwartet: YYYY-MM-DD.');

        return 1;
    }

    $rebuildService = app(WorktimeEvaluationRebuildService::class);

    if ($userIdOption !== null) {
        $user = User::query()->find($userIdOption);

        if ($user === null) {
            $this->error('Benutzer wurde nicht gefunden.');

            return 1;
        }

        $days = $rebuildService->rebuildForUserAndRange($user, $from, $to);

        $this->info("Evaluationen neu aufgebaut: user_id={$user->id}, tage={$days}, von={$from}, bis={$to}");

        return 0;
    }

    $result = $rebuildService->rebuildForAllUsers($from, $to, max(1, $chunkOption));

    $this->info('Evaluationen neu aufgebaut: '
        .'users='.$result['rebuilt_users']
        .', tage='.$result['rebuilt_days']
        .", von={$from}, bis={$to}");

    return 0;
})->purpose('Rebuild worktime evaluations for date range');

Schedule::call(function (): void {
    $from = now('UTC')->subDay()->toDateString();
    $to = now('UTC')->toDateString();

    app(WorktimeEvaluationRebuildService::class)
        ->rebuildForAllUsers($from, $to, 100);
})->dailyAt('02:15')->name('evaluations:daily-rebuild');
