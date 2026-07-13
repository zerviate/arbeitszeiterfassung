<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Models\Contract;
use App\Models\User;
use App\Services\ContractManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ContractWebController extends Controller
{
    public function __construct(
        private readonly ContractManagementService $contractManagementService,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Contract::class);

        $contracts = Contract::query()
            ->with(['user', 'createdBy'])
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('contracts.index', [
            'contracts' => $contracts,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Contract::class);

        $users = User::query()
            ->orderBy('name')
            ->get();

        return view('contracts.create', [
            'users' => $users,
        ]);
    }

    public function store(StoreContractRequest $request): RedirectResponse
    {
        $this->authorize('create', Contract::class);

        $targetUser = User::query()->findOrFail($request->integer('user_id'));

        $this->contractManagementService->create(
            actor: $request->user(),
            targetUser: $targetUser,
            payload: $request->validated(),
        );

        return redirect()
            ->route('contracts.index')
            ->with('success', 'Vertrag wurde angelegt.');
    }

    public function edit(Contract $contract): View
    {
        $contract->load(['user', 'createdBy']);

        $this->authorize('view', $contract);

        return view('contracts.edit', [
            'contract' => $contract,
        ]);
    }

    public function update(UpdateContractRequest $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        $this->contractManagementService->update(
            actor: $request->user(),
            contract: $contract,
            payload: $request->validated(),
        );

        return redirect()
            ->route('contracts.index')
            ->with('success', 'Vertrag wurde aktualisiert.');
    }
}
