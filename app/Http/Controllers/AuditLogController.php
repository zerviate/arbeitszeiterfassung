<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $validated = $request->validate([
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'event' => ['nullable', 'string', 'max:120'],
            'auditable_type' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = AuditLog::query()->with('actor')->latest();

        if (isset($validated['actor_id'])) {
            $query->where('actor_id', $validated['actor_id']);
        }

        if (isset($validated['event'])) {
            $query->where('event', $validated['event']);
        }

        if (isset($validated['auditable_type'])) {
            $query->where('auditable_type', $validated['auditable_type']);
        }

        $limit = $validated['limit'] ?? 50;

        return response()->json([
            'data' => $query->limit($limit)->get(),
        ]);
    }
}
