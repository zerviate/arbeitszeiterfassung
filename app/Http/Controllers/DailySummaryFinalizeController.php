<?php

namespace App\Http\Controllers;

use App\Models\DailyTimeSummary;
use App\Services\TimeFinalizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailySummaryFinalizeController extends Controller
{
    public function __construct(
        private readonly TimeFinalizeService $timeFinalizeService,
    ) {
    }

    public function store(Request $request, DailyTimeSummary $summary): RedirectResponse
    {
        $summary->loadMissing('user');

        $this->authorize('finalize', [DailyTimeSummary::class, $summary->user]);

        $this->timeFinalizeService->finalizeDay(
            targetUser: $summary->user,
            date: $summary->work_date->toDateString(),
            actor: $request->user(),
        );

        return back()->with('success', 'Tag wurde finalisiert.');
    }
}
