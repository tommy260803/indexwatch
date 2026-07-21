<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceStatus;
use App\Enums\RecommendedAction;
use App\Jobs\ExecuteMaintenanceActionJob;
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

    public function execute(Request $request): JsonResponse
    {
        Gate::authorize('update', Server::class);

        $request->validate([
            'action_ids' => 'required|array|min:1',
            'action_ids.*' => 'integer|exists:maintenance_actions,id',
        ]);

        $actions = MaintenanceAction::whereIn('id', $request->action_ids)
            ->whereIn('status', [MaintenanceStatus::Pending, MaintenanceStatus::Scheduled])
            ->get();

        if ($actions->isEmpty()) {
            return response()->json(['message' => 'No hay acciones válidas para ejecutar'], 422);
        }

        $dispatched = 0;
        foreach ($actions as $action) {
            ExecuteMaintenanceActionJob::dispatch($action->id);
            $dispatched++;
        }

        return response()->json([
            'message' => "{$dispatched} acción(es) encolada(s) para ejecución",
            'dispatched' => $dispatched,
        ]);
    }

    public function schedule(Request $request): JsonResponse
    {
        Gate::authorize('update', Server::class);

        $request->validate([
            'action_ids' => 'required|array|min:1',
            'action_ids.*' => 'integer|exists:maintenance_actions,id',
            'scheduled_for' => 'required|date|after:now',
        ]);

        $actions = MaintenanceAction::whereIn('id', $request->action_ids)
            ->whereIn('status', [MaintenanceStatus::Pending, MaintenanceStatus::Scheduled])
            ->get();

        if ($actions->isEmpty()) {
            return response()->json(['message' => 'No hay acciones válidas para programar'], 422);
        }

        $scheduled = 0;
        foreach ($actions as $action) {
            $action->update([
                'status' => MaintenanceStatus::Scheduled,
                'scheduled_for' => $request->scheduled_for,
            ]);
            $scheduled++;
        }

        return response()->json([
            'message' => "{$scheduled} acción(es) programada(s) para {$request->scheduled_for}",
            'scheduled' => $scheduled,
        ]);
    }

    public function updateType(Request $request, MaintenanceAction $action): JsonResponse
    {
        Gate::authorize('update', Server::class);

        $request->validate([
            'action_type' => 'required|string|in:REBUILD,REORGANIZE,UPDATE STATISTICS',
        ]);

        if (! in_array($action->status?->value, ['pending', 'scheduled'])) {
            return response()->json(['message' => 'No se puede modificar una acción en progreso'], 422);
        }

        $enumMap = [
            'REBUILD' => RecommendedAction::Rebuild,
            'REORGANIZE' => RecommendedAction::Reorganize,
            'UPDATE STATISTICS' => RecommendedAction::UpdateStatistics,
        ];

        $newType = $enumMap[$request->action_type];

        // Regenerate SQL script based on new type
        $idx = $action->sqlIndex;
        $server = $idx?->server;
        $dbName = $server?->database_name ?? $server?->name ?? 'default';
        $schema = $idx->schema_name ?? 'dbo';
        $table = $idx->table_name ?? '';
        $index = $idx->index_name ?? '';

        $sql = "ALTER INDEX [{$index}] ON [{$dbName}].[{$schema}].[{$table}]";
        if ($newType === RecommendedAction::Rebuild) {
            $sql .= " REBUILD WITH (ONLINE = ON, FILLFACTOR = 90);";
        } elseif ($newType === RecommendedAction::Reorganize) {
            $sql .= " REORGANIZE;";
        } else {
            $sql .= " UPDATE STATISTICS;";
        }

        $action->update([
            'action_type' => $newType,
            'sql_script' => $sql,
        ]);

        return response()->json(['data' => $action->fresh()]);
    }

    public function data(): JsonResponse
    {
        $pending = MaintenanceAction::with(['sqlIndex.server'])
            ->whereIn('status', [MaintenanceStatus::Pending, MaintenanceStatus::Scheduled])
            ->orderByDesc('created_at')
            ->get();

        $queue = $pending->map(function ($action) {
            $idx = $action->sqlIndex;
            $server = $idx?->server;
            return [
                'id' => $action->id,
                'index_name' => $idx?->index_name ?? '—',
                'table_name' => $idx?->table_name ?? '—',
                'schema_name' => $idx?->schema_name ?? 'dbo',
                'server_name' => $server?->name ?? '—',
                'fragmentation_percent' => $idx ? (float) $idx->fragmentation_percent : 0,
                'action_type' => $action->action_type?->value ?? 'REBUILD',
                'status' => $action->status?->value ?? 'pending',
                'sql_script' => $action->sql_script,
                'scheduled_for' => $action->scheduled_for?->toISOString(),
            ];
        });

        $rebuildCount = $pending->where('action_type', RecommendedAction::Rebuild)->count();
        $reorgCount = $pending->where('action_type', RecommendedAction::Reorganize)->count();
        $statsCount = $pending->where('action_type', RecommendedAction::UpdateStatistics)->count();

        $scripts = $pending->map(function ($action) {
            $idx = $action->sqlIndex;
            if (! $idx) return null;
            $server = $idx->server;
            $dbName = $server?->database_name ?? $server?->name ?? 'default';
            $schema = $idx->schema_name ?? 'dbo';
            $table = $idx->table_name;
            $index = $idx->index_name;
            $actionVal = $action->action_type?->value ?? 'REBUILD';

            $sql = "ALTER INDEX [{$index}] ON [{$dbName}].[{$schema}].[{$table}]";
            if ($actionVal === 'REBUILD') {
                $sql .= " REBUILD WITH (ONLINE = ON, FILLFACTOR = 90);";
            } elseif ($actionVal === 'REORGANIZE') {
                $sql .= " REORGANIZE;";
            } else {
                $sql .= " UPDATE STATISTICS;";
            }
            return $sql;
        })->filter()->values();

        return response()->json([
            'queue' => $queue,
            'summary' => [
                'total' => $pending->count(),
                'rebuild' => $rebuildCount,
                'reorganize' => $reorgCount,
                'stats' => $statsCount,
            ],
            'scripts' => $scripts,
        ]);
    }
}