<?php

namespace App\Services\Monitoring;

use App\Enums\ScanRunStatus;
use App\Enums\ScanStatus;
use App\Models\Server;
use App\Models\ServerScanRun;
use App\Services\SqlServer\SqlServerConnectionFactory;
use App\Services\SqlServer\SqlServerErrorSanitizer;
use App\Services\SqlServer\SqlServerInspectorService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ServerScanService
{
    public function __construct(
        private readonly SqlServerConnectionFactory $connections,
        private readonly SqlServerInspectorService $inspector,
        private readonly ScanPersistenceService $persistence,
        private readonly SqlServerErrorSanitizer $errors,
    ) {}

    public function scan(Server $server, ?string $correlationId = null): ServerScanRun
    {
        $startedAt = now();
        $startedNs = hrtime(true);
        $run = ServerScanRun::query()->firstOrNew([
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
        ]);

        if ($run->exists && in_array($run->status, [
            ScanRunStatus::Success,
            ScanRunStatus::Degraded,
        ], true)) {
            return $run;
        }

        $run->forceFill([
            'server_id' => $server->id,
            'status' => ScanRunStatus::Running,
            'started_at' => $startedAt,
            'finished_at' => null,
            'duration_ms' => null,
            'error' => null,
        ])->save();

        $server->forceFill([
            'last_scan_status' => ScanStatus::Running,
            'last_scan_error' => null,
        ])->save();

        try {
            $connection = $this->connections->connect($server);
            $inspection = $this->inspector->inspect($connection, $server->minimum_index_pages);
            $persistence = $this->persistence->persist($server, $inspection, now(), $run->id);
            $status = $inspection->warnings === []
                ? ScanRunStatus::Success
                : ScanRunStatus::Degraded;
            $finishedAt = now();
            $durationMs = $this->durationMs($startedNs);

            $run->forceFill([
                'status' => $status,
                'capabilities' => $inspection->capabilities->toArray(),
                'metrics' => $persistence->toArray(),
                'warnings' => $inspection->warnings ?: null,
                'finished_at' => $finishedAt,
                'duration_ms' => $durationMs,
            ])->save();

            $server->forceFill([
                'last_scanned_at' => $finishedAt,
                'last_scan_status' => $status === ScanRunStatus::Success
                    ? ScanStatus::Success
                    : ScanStatus::Degraded,
                'last_scan_error' => $inspection->warnings === []
                    ? null
                    : implode(' ', array_values($inspection->warnings)),
            ])->save();

            Log::info('IndexWatch SQL Server scan completed.', [
                'server_id' => $server->id,
                'scan_run_id' => $run->id,
                'correlation_id' => $run->correlation_id,
                'status' => $status->value,
                'duration_ms' => $durationMs,
                'metrics' => $persistence->toArray(),
            ]);

            return $run->refresh();
        } catch (Throwable $exception) {
            $safeError = $this->errors->sanitize($exception, $server);
            $finishedAt = now();
            $durationMs = $this->durationMs($startedNs);

            $run->forceFill([
                'status' => ScanRunStatus::Error,
                'error' => $safeError,
                'finished_at' => $finishedAt,
                'duration_ms' => $durationMs,
            ])->save();
            $server->forceFill([
                'last_scanned_at' => $finishedAt,
                'last_scan_status' => ScanStatus::Error,
                'last_scan_error' => $safeError,
            ])->save();

            Log::warning('IndexWatch SQL Server scan failed.', [
                'server_id' => $server->id,
                'scan_run_id' => $run->id,
                'correlation_id' => $run->correlation_id,
                'duration_ms' => $durationMs,
                'error' => $safeError,
            ]);

            throw $exception;
        } finally {
            $this->connections->disconnect($server);
        }
    }

    private function durationMs(int $startedNs): int
    {
        return (int) round((hrtime(true) - $startedNs) / 1_000_000);
    }
}
