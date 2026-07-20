<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SqlIndex;
use App\Models\Alert;
use App\Models\Server;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    public function data()
    {
        $indexes = SqlIndex::with('server')->active()->get();

        $total = $indexes->count();
        $critical = $indexes->where('fragmentation_percent', '>', 30)->count();
        $warning = $indexes->where('fragmentation_percent', '>=', 5)->where('fragmentation_percent', '<=', 30)->count();
        $ok = $indexes->where('fragmentation_percent', '<', 5)->count();

        $alerts = Alert::with(['sqlIndex', 'server', 'approvedBy'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($alert) {
                $statusStr = $alert->getStatusLabel();
                $severityColor = $alert->getSeverityColor();

                $subjectDisplay = $alert->getSubjectDisplay();
                $actionLabel = $alert->recommended_action?->value ?? '—';

                $text = '';
                if ($alert->isClosed()) {
                    $lastAction = collect($alert->getActionHistory())->last();
                    $actionTaken = $lastAction['action'] ?? $actionLabel;
                    $text = "{$actionTaken} completado en <code>{$subjectDisplay}</code>. ";
                    if ($alert->sqlIndex) {
                        $text .= "Fragmentación actual: {$alert->sqlIndex->fragmentation_percent}%.";
                    }
                } else {
                    $text = "<code>{$subjectDisplay}</code> — {$alert->getTypeLabel()} ({$alert->getSeverityLabel()}). Acción recomendada: {$actionLabel}.";
                }

                return [
                    'status' => $statusStr,
                    'status_color' => $severityColor,
                    'text' => $text,
                    'time_ago' => $alert->created_at->diffForHumans(),
                    'alert_id' => $alert->id,
                    'severity' => $alert->severity?->value,
                    'type' => $alert->alert_type?->value,
                ];
            });

        $indexesList = $indexes->map(function ($idx) {
            $action = 'OK';
            if ($idx->fragmentation_percent > 30) {
                $action = 'REBUILD';
            } elseif ($idx->fragmentation_percent >= 5) {
                $action = 'REORGANIZE';
            }

            return [
                'server' => $idx->server->name ?? '—',
                'schema' => $idx->schema_name,
                'table' => $idx->table_name,
                'index' => $idx->index_name,
                'frag' => (float) $idx->fragmentation_percent,
                'size' => $idx->size_mb ? (float) $idx->size_mb : 0,
                'lastReorg' => $idx->last_user_scan_at ? $idx->last_user_scan_at->format('d/m/Y') : 'N/A',
                'action' => $action,
                'reads' => $idx->getTotalReads(),
                'writes' => $idx->getTotalWrites(),
                'type' => $idx->type?->value,
                'is_pk' => $idx->is_primary_key,
                'is_unique' => $idx->is_unique,
            ];
        });

        $servers = Server::active()->withCount('sqlIndexes')->get()->map(function ($server) {
            return [
                'id' => $server->id,
                'name' => $server->name,
                'host' => $server->host,
                'health_score' => $server->health_score,
                'health_score_details' => $server->health_score_details,
                'last_scanned_at' => $server->last_scanned_at?->toISOString(),
                'last_scan_status' => $server->last_scan_status?->value,
                'indexes_count' => $server->sql_indexes_count,
            ];
        });

        return response()->json([
            'kpis' => [
                'total' => $total,
                'critical' => $critical,
                'warning' => $warning,
                'ok' => $ok,
            ],
            'alerts' => $alerts,
            'indexes' => $indexesList,
            'servers' => $servers,
        ]);
    }
}