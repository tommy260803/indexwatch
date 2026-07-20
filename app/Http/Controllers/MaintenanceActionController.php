<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceAction;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MaintenanceActionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Server::class);

        $actions = MaintenanceAction::with(['alert', 'server', 'sqlIndex'])
            ->when($request->server_id, fn ($q) => $q->where('server_id', $request->server_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->action_type, fn ($q) => $q->where('action_type', $request->action_type))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 25);

        return response()->json($actions);
    }

    public function show(MaintenanceAction $action): JsonResponse
    {
        Gate::authorize('view', Server::class);
        $action->load(['alert', 'server', 'sqlIndex', 'initiatedBy']);
        return response()->json(['data' => $action]);
    }

    public function cancel(Request $request, MaintenanceAction $action): JsonResponse
    {
        Gate::authorize('update', Server::class);

        if (! $action->canBeCancelled()) {
            return response()->json(['message' => 'Action cannot be cancelled in current state'], 422);
        }

        $action->cancel();

        return response()->json(['data' => $action->fresh()]);
    }
}