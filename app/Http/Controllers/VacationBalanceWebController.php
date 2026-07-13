<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVacationBalanceRequest;
use App\Http\Requests\UpdateVacationBalanceRequest;
use App\Models\User;
use App\Models\VacationBalance;
use App\Services\VacationBalanceService;
use App\Support\VisibleUserScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VacationBalanceWebController extends Controller
{
    public function __construct(
        private readonly VacationBalanceService $vacationBalanceService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', VacationBalance::class);

        $actor = $request->user();
        $year = (int) $request->input('year', now()->setTimezone($actor->timezone ?: 'UTC')->format('Y'));

        $users = VisibleUserScope::absenceUsers($actor);

        $summaries = $users->map(function (User $user) use ($year): array {
            return [
                'user' => $user,
                'summary' => $this->vacationBalanceService->getYearSummary($user, $year),
            ];
        });

        return view('vacation-balances.index', [
            'year' => $year,
            'summaries' => $summaries,
            'canManage' => Gate::allows('create', VacationBalance::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', VacationBalance::class);

        $users = User::query()
            ->orderBy('name')
            ->get();

        return view('vacation-balances.create', [
            'users' => $users,
            'prefillUserId' => $request->input('user_id'),
            'prefillYear' => $request->input('year'),
        ]);
    }

    public function store(StoreVacationBalanceRequest $request): RedirectResponse
    {
        $this->authorize('create', VacationBalance::class);

        $balance = $this->vacationBalanceService->createBalance(
            actor: $request->user(),
            payload: $request->validated(),
        );

        return redirect()
            ->route('vacation-balances.index', ['year' => $balance->year])
            ->with('success', 'Urlaubskonto wurde angelegt.');
    }

    public function edit(VacationBalance $vacationBalance): View
    {
        $vacationBalance->load('user');

        $this->authorize('update', $vacationBalance);

        return view('vacation-balances.edit', [
            'vacationBalance' => $vacationBalance,
        ]);
    }

    public function update(UpdateVacationBalanceRequest $request, VacationBalance $vacationBalance): RedirectResponse
    {
        $this->authorize('update', $vacationBalance);

        $updated = $this->vacationBalanceService->updateBalance(
            actor: $request->user(),
            balance: $vacationBalance,
            payload: $request->validated(),
        );

        return redirect()
            ->route('vacation-balances.index', ['year' => $updated->year])
            ->with('success', 'Urlaubskonto wurde aktualisiert.');
    }
}
