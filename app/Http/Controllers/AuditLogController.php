<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::with(['server', 'auditable'])
            ->when($request->server_id, fn ($q) => $q->where('server_id', $request->server_id))
            ->when($request->action, fn ($q) => $q->where('action', $request->action))
            ->when($request->actor_type, fn ($q) => $q->where('actor_type', $request->actor_type))
            ->when($request->source, fn ($q) => $q->where('source', $request->source))
            ->when($request->date_from, fn ($q) => $q->where('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->where('created_at', '<=', $request->date_to))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 50);

        return response()->json($logs);
    }
}