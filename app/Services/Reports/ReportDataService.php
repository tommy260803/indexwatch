<?php

namespace App\Services\Reports;

use App\Domain\Analytics\DTO\Finding;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\GeneratedReport;
use App\Models\IndexSnapshot;
use App\Models\MaintenanceAction;
use App\Models\Server;
use App\Models\SqlIndex;
use App\Models\StatisticsStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportDataService
{
    public function getReportData(array $filters): array
    {
        $serverId = $filters['server_id'] ?? null;
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : Carbon::now()->subDays(30);
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : Carbon::now();
        $alertTypes = $filters['alert_types'] ?? [];

        $servers = Server::query()
            ->when($serverId, fn ($q) => $q->where('id', $serverId))
            ->where('is_active', true)
            ->with(['sqlIndexes', 'alerts', 'maintenanceWindows', 'statisticsStatuses'])
            ->get();

        $data = [
            'generated_at' => now()->toISOString(),
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'filters' => $filters,
            'servers' => $servers->map(fn (Server $s) => $this->getServerReportData($s, $startDate, $endDate, $alertTypes))->toArray(),
            'summary' => [
                'total_servers' => $servers->count(),
                'total_alerts' => 0,
                'critical_alerts' => 0,
                'warning_alerts' => 0,
                'info_alerts' => 0,
                'health_score_avg' => 0,
            ],
        ];

        $this->computeSummary($data);

        return $data;
    }

    private function getServerReportData(Server $server, Carbon $startDate, Carbon $endDate, array $alertTypes): array
    {
        $alertsQuery = $server->alerts()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($alertTypes, fn ($q) => $q->whereIn('alert_type', $alertTypes));

        $alerts = $alertsQuery->get();
        $indexes = $server->sqlIndexes()->active()->get();
        $snapshots = IndexSnapshot::query()
            ->whereHas('sqlIndex.server', fn ($q) => $q->where('id', $server->id))
            ->whereBetween('scanned_at', [$startDate, $endDate])
            ->latest('scanned_at')
            ->get();

        $healthScore = $server->health_score ?? null;
        $healthScoreDetails = $server->health_score_details ?? [];

        return [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'host' => $server->host,
                'database_name' => $server->database_name,
                'health_score' => $healthScore,
                'health_score_details' => $healthScoreDetails,
                'last_scanned_at' => $server->last_scanned_at?->toISOString(),
                'last_scan_status' => $server->last_scan_status?->value,
                'timezone' => $server->timezone,
            ],
            'alerts' => $alerts->map(fn (Alert $a) => $this->formatAlert($a))->toArray(),
            'indexes' => $indexes->map(fn (SqlIndex $i) => $this->formatIndex($i))->toArray(),
            'snapshots' => $snapshots->map(fn (IndexSnapshot $s) => $this->formatSnapshot($s))->toArray(),
            'stats' => [
                'total_indexes' => $indexes->count(),
                'critical_fragmentation' => $indexes->filter(fn (SqlIndex $i) => $i->isCriticalFragmented())->count(),
                'warning_fragmentation' => $indexes->filter(fn (SqlIndex $i) => $i->isFragmented() && ! $i->isCriticalFragmented())->count(),
                'healthy_indexes' => $indexes->filter(fn (SqlIndex $i) => ! $i->isFragmented())->count(),
                'alerts_pending' => $alerts->whereIn('status', ['pending', 'sent', 'awaiting_response'])->count(),
                'alerts_approved' => $alerts->where('status', 'approved')->count(),
                'alerts_scheduled' => $alerts->where('status', 'scheduled')->count(),
                'alerts_running' => $alerts->where('status', 'running')->count(),
                'alerts_succeeded' => $alerts->where('status', 'succeeded')->count(),
                'alerts_failed' => $alerts->where('status', 'failed')->count(),
                'alerts_dismissed' => $alerts->where('status', 'dismissed')->count(),
            ],
        ];
    }

    private function formatAlert(Alert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->alert_type?->value,
            'type_label' => $alert->getTypeLabel(),
            'severity' => $alert->severity?->value,
            'severity_label' => $alert->getSeverityLabel(),
            'severity_color' => $alert->getSeverityColor(),
            'status' => $alert->status?->value,
            'status_label' => $alert->getStatusLabel(),
            'recommended_action' => $alert->recommended_action?->value,
            'fragmentation_percent' => $alert->fragmentation_percent,
            'metadata' => $alert->metadata,
            'subject_display' => $alert->getSubjectDisplay(),
            'created_at' => $alert->created_at?->toISOString(),
            'approved_at' => $alert->approved_at?->toISOString(),
            'scheduled_for' => $alert->scheduled_for?->toISOString(),
            'executed_at' => $alert->executed_at?->toISOString(),
            'resolved_at' => $alert->resolved_at?->toISOString(),
            'approved_by' => $alert->approvedBy?->name,
            'action_history' => $alert->getActionHistory(),
        ];
    }

    private function formatIndex(SqlIndex $index): array
    {
        return [
            'id' => $index->id,
            'schema_name' => $index->schema_name,
            'table_name' => $index->table_name,
            'index_name' => $index->index_name,
            'qualified_name' => $index->qualifiedName(),
            'type' => $index->type?->value,
            'is_primary_key' => $index->is_primary_key,
            'is_unique' => $index->is_unique,
            'is_disabled' => $index->is_disabled,
            'fragmentation_percent' => $index->fragmentation_percent,
            'size_mb' => $index->size_mb,
            'page_count' => $index->page_count,
            'fill_factor' => $index->fill_factor,
            'optimal_fill_factor' => $index->optimal_fill_factor,
            'fill_factor_reason' => $index->fill_factor_reason,
            'user_seeks' => $index->user_seeks,
            'user_scans' => $index->user_scans,
            'user_lookups' => $index->user_lookups,
            'user_updates' => $index->user_updates,
            'last_checked_at' => $index->last_checked_at?->toISOString(),
            'status' => $index->status?->value,
            'total_reads' => $index->getTotalReads(),
            'total_writes' => $index->getTotalWrites(),
            'read_write_ratio' => $index->getReadWriteRatio(),
        ];
    }

    private function formatSnapshot(IndexSnapshot $snapshot): array
    {
        return [
            'id' => $snapshot->id,
            'sql_index_id' => $snapshot->sql_index_id,
            'fragmentation_percent' => $snapshot->fragmentation_percent,
            'size_mb' => $snapshot->size_mb,
            'page_count' => $snapshot->page_count,
            'record_count' => $snapshot->record_count,
            'fill_factor' => $snapshot->fill_factor,
            'scanned_at' => $snapshot->scanned_at?->toISOString(),
        ];
    }

    private function computeSummary(array &$data): void
    {
        $totalAlerts = 0;
        $critical = 0;
        $warning = 0;
        $info = 0;
        $healthScores = [];

        foreach ($data['servers'] as $serverData) {
            foreach ($serverData['alerts'] as $alert) {
                $totalAlerts++;
                match ($alert['severity']) {
                    'critical' => $critical++,
                    'warning' => $warning++,
                    'info' => $info++,
                };
            }

            if ($serverData['server']['health_score'] !== null) {
                $healthScores[] = $serverData['server']['health_score'];
            }
        }

        $data['summary'] = [
            'total_servers' => count($data['servers']),
            'total_alerts' => $totalAlerts,
            'critical_alerts' => $critical,
            'warning_alerts' => $warning,
            'info_alerts' => $info,
            'health_score_avg' => $healthScores ? round(array_sum($healthScores) / count($healthScores), 1) : null,
        ];
    }

    public function getAuditLogs(array $filters): array
    {
        $query = AuditLog::query()
            ->with(['server', 'maintenanceAction', 'alert'])
            ->orderByDesc('created_at');

        if (! empty($filters['server_id'])) {
            $query->where('server_id', $filters['server_id']);
        }

        if (! empty($filters['alert_id'])) {
            $query->where('alert_id', $filters['alert_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['actor_type'])) {
            $query->where('actor_type', $filters['actor_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from']));
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to']));
        }

        return $query->paginate($filters['per_page'] ?? 50)->toArray();
    }

    public function getMaintenanceActions(array $filters): array
    {
        $query = MaintenanceAction::query()
            ->with(['alert.server', 'alert.sqlIndex'])
            ->orderByDesc('created_at');

        if (! empty($filters['server_id'])) {
            $query->whereHas('alert', fn ($q) => $q->where('server_id', $filters['server_id']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from']));
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to']));
        }

        return $query->paginate($filters['per_page'] ?? 50)->toArray();
    }
}