<?php

namespace App\Services\Reports;

use App\Models\GeneratedReport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class ReportExportService
{
    private const STORAGE_DISK = 'local';
    private const STORAGE_PATH = 'private/reports';

    public function generate(GeneratedReport $report): void
    {
        try {
            $report->forceFill([
                'status' => 'generating',
                'metadata' => array_merge($report->metadata ?? [], [
                    'started_at' => now()->toISOString(),
                ]),
            ])->save();

            $data = $this->buildReportData($report);

            $fileName = $this->generateFileName($report);
            $filePath = self::STORAGE_PATH . '/' . $fileName;

            $disk = Storage::disk(self::STORAGE_DISK);
            $disk->makeDirectory(dirname($filePath));

            // Generate HTML report (can be opened in browser and printed to PDF)
            $html = View::make('reports.html', ['data' => $data])->render();
            $disk->put($filePath, $html);

            $report->forceFill([
                'status' => 'completed',
                'file_path' => $filePath,
                'expires_at' => now()->addDays(7),
                'metadata' => array_merge($report->metadata ?? [], [
                    'completed_at' => now()->toISOString(),
                    'file_size' => $disk->size($filePath),
                ]),
            ])->save();
        } catch (\Throwable $e) {
            $report->forceFill([
                'status' => 'failed',
                'metadata' => array_merge($report->metadata ?? [], [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString(),
                ]),
            ])->save();

            throw $e;
        }
    }

    private function buildReportData(GeneratedReport $report): array
    {
        $filters = $report->filters ?? [];
        $serverId = $filters['server_id'] ?? null;
        $startDate = isset($filters['start_date']) ? \Carbon\Carbon::parse($filters['start_date']) : \Carbon\Carbon::now()->subDays(30);
        $endDate = isset($filters['end_date']) ? \Carbon\Carbon::parse($filters['end_date']) : \Carbon\Carbon::now();

        $servers = \App\Models\Server::query()
            ->when($serverId, fn ($q) => $q->where('id', $serverId))
            ->where('is_active', true)
            ->with(['sqlIndexes', 'alerts', 'maintenanceWindows', 'statisticsStatuses'])
            ->get();

        $totalIndexes = 0;
        $criticalFragmentation = 0;
        $warningFragmentation = 0;
        $healthyIndexes = 0;
        $totalSizeMb = 0;

        $indexDetails = [];
        $alertsSummary = [];
        $maintenanceSummary = [];
        $auditSummary = [];

        foreach ($servers as $server) {
            foreach ($server->sqlIndexes as $index) {
                $totalIndexes++;
                $totalSizeMb += $index->size_mb ?? 0;

                $frag = $index->fragmentation_percent ?? 0;
                if ($frag >= ($server->critical_threshold ?? 50)) {
                    $criticalFragmentation++;
                } elseif ($frag >= ($server->warning_threshold ?? 30)) {
                    $warningFragmentation++;
                } else {
                    $healthyIndexes++;
                }

                $indexDetails[] = [
                    'server' => $server->name,
                    'schema' => $index->schema_name,
                    'table' => $index->table_name,
                    'index' => $index->index_name,
                    'type' => $index->type?->value,
                    'fragmentation' => $frag,
                    'size_mb' => $index->size_mb,
                    'page_count' => $index->page_count,
                    'fill_factor' => $index->fill_factor,
                    'reads' => $index->getTotalReads(),
                    'writes' => $index->getTotalWrites(),
                    'last_checked' => $index->last_checked_at?->format('Y-m-d H:i'),
                ];
            }

            $serverAlerts = $server->alerts()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($serverAlerts as $alert) {
                $alertsSummary[] = [
                    'server' => $server->name,
                    'type' => $alert->alert_type?->value,
                    'type_label' => $alert->getTypeLabel(),
                    'severity' => $alert->severity?->value,
                    'severity_label' => $alert->getSeverityLabel(),
                    'status' => $alert->status?->value,
                    'status_label' => $alert->getStatusLabel(),
                    'subject' => $alert->getSubjectDisplay(),
                    'subject_display' => $alert->getSubjectDisplay(),
                    'action' => $alert->recommended_action?->value,
                    'fragmentation_percent' => $alert->fragmentation_percent,
                    'created_at' => $alert->created_at->format('Y-m-d H:i'),
                    'resolved_at' => $alert->resolved_at?->format('Y-m-d H:i'),
                ];
            }

            $serverMaintenance = $server->maintenanceActions()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($serverMaintenance as $action) {
                $maintenanceSummary[] = [
                    'server' => $server->name,
                    'type' => $action->action_type?->value,
                    'status' => $action->status?->value,
                    'scheduled_for' => $action->scheduled_for?->format('Y-m-d H:i'),
                    'started_at' => $action->started_at?->format('Y-m-d H:i'),
                    'finished_at' => $action->finished_at?->format('Y-m-d H:i'),
                    'result' => $action->result,
                    'error' => $action->error,
                ];
            }
        }

        $auditLogs = \App\Models\AuditLog::query()
            ->when($serverId, fn ($q) => $q->where('server_id', $serverId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get();

        foreach ($auditLogs as $log) {
            $auditSummary[] = [
                'server' => $log->server?->name,
                'actor' => $log->actor_name,
                'source' => $log->source?->value,
                'action' => $log->action?->value,
                'status' => $log->status?->value,
                'description' => $log->description,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $staleStats = \App\Models\StatisticsStatus::query()
            ->when($serverId, fn ($q) => $q->where('server_id', $serverId))
            ->where('modification_ratio', '>=', $servers->first()?->stats_stale_threshold ?? 20)
            ->whereBetween('scanned_at', [$startDate, $endDate])
            ->get();

        // Build servers array for HTML template
        $serversData = [];
        foreach ($servers as $server) {
            $serverAlerts = $server->alerts()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->get();

            $serverIndexes = $server->sqlIndexes()->active()->get();

            $serversData[] = [
                'server' => [
                    'id' => $server->id,
                    'name' => $server->name,
                    'host' => $server->host,
                    'database_name' => $server->database_name,
                    'health_score' => $server->health_score,
                    'health_score_details' => $server->health_score_details,
                    'last_scanned_at' => $server->last_scanned_at?->toISOString(),
                    'last_scan_status' => $server->last_scan_status?->value,
                    'timezone' => $server->timezone,
                ],
                'alerts' => $serverAlerts->map(fn (Alert $a) => [
                    'id' => $a->id,
                    'type' => $a->alert_type?->value,
                    'type_label' => $a->getTypeLabel(),
                    'severity' => $a->severity?->value,
                    'severity_label' => $a->getSeverityLabel(),
                    'severity_color' => $a->getSeverityColor(),
                    'status' => $a->status?->value,
                    'status_label' => $a->getStatusLabel(),
                    'recommended_action' => $a->recommended_action?->value,
                    'fragmentation_percent' => $a->fragmentation_percent,
                    'metadata' => $a->metadata,
                    'subject_display' => $a->getSubjectDisplay(),
                    'created_at' => $a->created_at?->toISOString(),
                    'approved_at' => $a->approved_at?->toISOString(),
                    'scheduled_for' => $a->scheduled_for?->toISOString(),
                    'executed_at' => $a->executed_at?->toISOString(),
                    'resolved_at' => $a->resolved_at?->toISOString(),
                    'approved_by' => $a->approvedBy?->name,
                    'action_history' => $a->getActionHistory(),
                ])->toArray(),
                'indexes' => $serverIndexes->map(fn (SqlIndex $i) => [
                    'id' => $i->id,
                    'schema_name' => $i->schema_name,
                    'table_name' => $i->table_name,
                    'index_name' => $i->index_name,
                    'qualified_name' => $i->qualifiedName(),
                    'type' => $i->type?->value,
                    'is_primary_key' => $i->is_primary_key,
                    'is_unique' => $i->is_unique,
                    'is_disabled' => $i->is_disabled,
                    'fragmentation_percent' => $i->fragmentation_percent,
                    'size_mb' => $i->size_mb,
                    'page_count' => $i->page_count,
                    'fill_factor' => $i->fill_factor,
                    'optimal_fill_factor' => $i->optimal_fill_factor,
                    'fill_factor_reason' => $i->fill_factor_reason,
                    'user_seeks' => $i->user_seeks,
                    'user_scans' => $i->user_scans,
                    'user_lookups' => $i->user_lookups,
                    'user_updates' => $i->user_updates,
                    'last_checked_at' => $i->last_checked_at?->toISOString(),
                    'status' => $i->status?->value,
                    'total_reads' => $i->getTotalReads(),
                    'total_writes' => $i->getTotalWrites(),
                    'read_write_ratio' => $i->getReadWriteRatio(),
                ])->toArray(),
                'snapshots' => [],
                'stats' => [
                    'total_indexes' => $serverIndexes->count(),
                    'critical_fragmentation' => $serverIndexes->filter(fn (SqlIndex $i) => $i->isCriticalFragmented())->count(),
                    'warning_fragmentation' => $serverIndexes->filter(fn (SqlIndex $i) => $i->isFragmented() && ! $i->isCriticalFragmented())->count(),
                    'healthy_indexes' => $serverIndexes->filter(fn (SqlIndex $i) => ! $i->isFragmented())->count(),
                    'alerts_pending' => $serverAlerts->whereIn('status', ['pending', 'sent', 'awaiting_response'])->count(),
                    'alerts_approved' => $serverAlerts->where('status', 'approved')->count(),
                    'alerts_scheduled' => $serverAlerts->where('status', 'scheduled')->count(),
                    'alerts_running' => $serverAlerts->where('status', 'running')->count(),
                    'alerts_succeeded' => $serverAlerts->where('status', 'succeeded')->count(),
                    'alerts_failed' => $serverAlerts->where('status', 'failed')->count(),
                    'alerts_dismissed' => $serverAlerts->where('status', 'dismissed')->count(),
                ],
            ];
        }

        return [
            'meta' => [
                'generated_at' => now()->format('d/m/Y H:i:s'),
                'generated_by' => $report->requestedBy?->name ?? 'Sistema',
                'period' => [
                    'start' => $startDate->format('d/m/Y'),
                    'end' => $endDate->format('d/m/Y'),
                ],
                'filters' => $filters,
                'algorithm_version' => '1.0',
            ],
            'summary' => [
                'total_servers' => $servers->count(),
                'total_alerts' => count($alertsSummary),
                'critical_alerts' => count(array_filter($alertsSummary, fn ($a) => $a['severity'] === 'critical')),
                'warning_alerts' => count(array_filter($alertsSummary, fn ($a) => $a['severity'] === 'warning')),
                'info_alerts' => count(array_filter($alertsSummary, fn ($a) => $a['severity'] === 'info')),
                'health_score_avg' => null,
            ],
            'servers' => $serversData,
            'fragmentation' => $indexDetails,
            'statistics' => $staleStats->map(fn ($s) => [
                'server' => $s->server?->name,
                'schema' => $s->schema_name,
                'table' => $s->table_name,
                'stats_name' => $s->stats_name,
                'row_count' => $s->row_count,
                'modification_count' => $s->modification_count,
                'modification_percent' => round($s->modification_ratio, 2),
                'last_updated' => $s->last_updated_at?->format('Y-m-d'),
            ])->toArray(),
            'alerts' => $alertsSummary,
            'maintenance' => $maintenanceSummary,
            'audit' => $auditSummary,
            'annex' => [
                'limitations' => [
                    'Las métricas DMV se reinician al reiniciar SQL Server',
                    'Missing indexes son sugerencias, no verdades absolutas',
                    'Page splits requieren muestreo temporal para deltas',
                    'Fill factor óptimo es una estimación basada en patrones',
                ],
            ],
        ];
    }

    private function generateFileName(GeneratedReport $report): string
    {
        $date = now()->format('Ymd_His');
        $server = $report->server ? str_replace(' ', '_', $report->server->name) : 'all';

        return "IndexWatch_Report_{$server}_{$date}.html";
    }

    public function download(GeneratedReport $report): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        if (! $report->file_path || ! Storage::disk(self::STORAGE_DISK)->exists($report->file_path)) {
            abort(404, 'Reporte no encontrado o expirado');
        }

        if ($report->isExpired()) {
            abort(410, 'El reporte ha expirado');
        }

        $path = Storage::disk(self::STORAGE_DISK)->path($report->file_path);
        $fileName = basename($report->file_path);

        return response()->download($path, $fileName)->deleteFileAfterSend(false);
    }
}