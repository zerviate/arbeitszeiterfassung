<?php

namespace App\Http\Controllers;

use App\Models\DailyTimeSummary;
use App\Models\User;
use App\Services\TimeFinalizeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TimeFinalizeController extends Controller
{
    public function __construct(private readonly TimeFinalizeService $timeFinalizeService)
    {
    }

    public function finalize(Request $request, string $date): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->assertValidDate($date);

        $targetUser = isset($validated['user_id'])
            ? User::query()->findOrFail($validated['user_id'])
            : $request->user();

        $this->authorize('finalize', [DailyTimeSummary::class, $targetUser]);

        $summary = $this->timeFinalizeService->finalizeDay(
            targetUser: $targetUser,
            date: $date,
            actor: $request->user(),
        );

        return response()->json(['data' => $summary]);
    }

    public function unfinalize(Request $request, string $date): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->assertValidDate($date);

        $targetUser = isset($validated['user_id'])
            ? User::query()->findOrFail($validated['user_id'])
            : $request->user();

        $this->authorize('unfinalize', [DailyTimeSummary::class, $targetUser]);

        $summary = $this->timeFinalizeService->unfinalizeDay(
            targetUser: $targetUser,
            date: $date,
            actor: $request->user(),
        );

        return response()->json(['data' => $summary]);
    }

    private function assertValidDate(string $date): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw ValidationException::withMessages([
                'date' => 'Datum muss im Format YYYY-MM-DD angegeben werden.',
            ]);
        }

        $parsed = Carbon::createFromFormat('Y-m-d', $date, 'UTC');

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw ValidationException::withMessages([
                'date' => 'Datum ist ungueltig.',
            ]);
        }
    }
}
