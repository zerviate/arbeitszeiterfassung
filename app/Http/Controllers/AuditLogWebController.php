<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $validated = $request->validate([
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'event' => ['nullable', 'string', 'max:120'],
        ]);

        $eventOptions = AuditLog::query()
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        $logs = AuditLog::query()
            ->with('actor')
            ->when(isset($validated['actor_id']), fn ($query) => $query->where('actor_id', $validated['actor_id']))
            ->when(isset($validated['event']), fn ($query) => $query->where('event', $validated['event']))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'eventOptions' => $eventOptions,
        ]);
    }
}
