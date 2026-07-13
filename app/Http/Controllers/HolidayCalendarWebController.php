<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHolidayCalendarEntryRequest;
use App\Http\Requests\UpdateHolidayCalendarEntryRequest;
use App\Models\HolidayCalendarEntry;
use App\Services\HolidayCalendarManagementService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HolidayCalendarWebController extends Controller
{
    public function __construct(
        private readonly HolidayCalendarManagementService $holidayCalendarManagementService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', HolidayCalendarEntry::class);

        $month = (string) $request->input('month', now('UTC')->format('Y-m'));

        try {
            $monthDate = Carbon::createFromFormat('Y-m', $month, 'UTC');
        } catch (\Throwable $exception) {
            $monthDate = now('UTC')->startOfMonth();
            $month = $monthDate->format('Y-m');
        }

        $year = (int) $monthDate->format('Y');
        $startOfMonth = $monthDate->copy()->startOfMonth();
        $endOfMonth = $monthDate->copy()->endOfMonth();

        $entries = HolidayCalendarEntry::query()
            ->with('createdBy')
            ->whereBetween('holiday_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->orderBy('holiday_date')
            ->get();

        return view('holidays.index', [
            'entries' => $entries,
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', HolidayCalendarEntry::class);

        return view('holidays.create');
    }

    public function store(StoreHolidayCalendarEntryRequest $request): RedirectResponse
    {
        $this->authorize('create', HolidayCalendarEntry::class);

        $this->holidayCalendarManagementService->create(
            actor: $request->user(),
            payload: $request->validated(),
        );

        return redirect()->route('holidays.index')->with('success', 'Feiertag wurde angelegt.');
    }

    public function edit(HolidayCalendarEntry $holiday): View
    {
        $this->authorize('view', $holiday);

        return view('holidays.edit', [
            'holiday' => $holiday,
        ]);
    }

    public function update(UpdateHolidayCalendarEntryRequest $request, HolidayCalendarEntry $holiday): RedirectResponse
    {
        $this->authorize('update', $holiday);

        $this->holidayCalendarManagementService->update(
            actor: $request->user(),
            entry: $holiday,
            payload: $request->validated(),
        );

        return redirect()->route('holidays.index')->with('success', 'Feiertag wurde aktualisiert.');
    }

    public function toggle(Request $request, HolidayCalendarEntry $holiday): RedirectResponse
    {
        $this->authorize('update', $holiday);

        $newStatus = ! (bool) $holiday->is_active;

        $this->holidayCalendarManagementService->update(
            actor: $request->user(),
            entry: $holiday,
            payload: [
                'holiday_date' => $holiday->holiday_date?->toDateString(),
                'name' => (string) $holiday->name,
                'is_active' => $newStatus,
            ],
        );

        return back()->with('toast', [
            'message' => $newStatus ? 'Feiertag aktiviert.' : 'Feiertag deaktiviert.',
            'variant' => $newStatus ? 'success' : 'neutral',
        ]);
    }

    public function destroy(Request $request, HolidayCalendarEntry $holiday): RedirectResponse
    {
        $this->authorize('delete', $holiday);

        $this->holidayCalendarManagementService->delete(
            actor: $request->user(),
            entry: $holiday,
        );

        return redirect()->route('holidays.index')->with('success', 'Feiertag wurde gelöscht.');
    }
}
