<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceWindow;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MaintenanceWindowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Server::class);

        $windows = MaintenanceWindow::with('server')
            ->when($request->server_id, fn ($q) => $q->where('server_id', $request->server_id))
            ->orderBy('server_id')
            ->orderBy('day_of_week')
            ->get();

        return response()->json(['data' => $windows]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('update', Server::class);

        $validated = $request->validate([
            'server_id' => 'required|exists:servers,id',
            'day_of_week' => 'required|integer|between:0,6',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'active' => 'boolean',
        ]);

        $window = MaintenanceWindow::create($validated);

        return response()->json(['data' => $window], 201);
    }

    public function update(Request $request, MaintenanceWindow $window): JsonResponse
    {
        Gate::authorize('update', Server::class);

        $validated = $request->validate([
            'day_of_week' => 'integer|between:0,6',
            'start_time' => 'date_format:H:i',
            'end_time' => 'date_format:H:i',
            'active' => 'boolean',
        ]);

        $window->update($validated);

        return response()->json(['data' => $window]);
    }

    public function destroy(MaintenanceWindow $window): JsonResponse
    {
        Gate::authorize('update', Server::class);
        $window->delete();
        return response()->json(['message' => 'Deleted'], 200);
    }
}