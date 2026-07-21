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
        // Guardamos el inicio para poder registrar duración real y correlacionar
        // todos los eventos de este escaneo en logs, BD y alertas.
        $startedAt = now();
        $startedNs = hrtime(true);
        $run = ServerScanRun::query()->firstOrNew([
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
        ]);

        // La correlación hace que un reintento no vuelva a persistir el mismo resultado.
        // Esto es importante porque el job puede reejecutarse por fallos de cola.
        if ($run->exists && in_array($run->status, [
            ScanRunStatus::Success,
            ScanRunStatus::Degraded,
        ], true)) {
            return $run;
        }

        // Antes de conectar, dejamos evidencia de que el proceso arrancó.
        // Así el dashboard puede reflejar actividad aunque la ejecución tarde.
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
            // Flujo principal:
            // 1) abrir conexión temporal al servidor monitoreado
            // 2) inspeccionar inventario, uso, fragmentación y estadísticas
            // 3) guardar resultados y derivar alertas
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
            // El error crudo puede revelar cadenas de conexión, nombres internos o
            // detalles de SQL Server. Se guarda una versión sanitizada.
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
            // La conexión es temporal; siempre se destruye para no acumular recursos.
            $this->connections->disconnect($server);
        }
    }

    private function durationMs(int $startedNs): int
    {
        return (int) round((hrtime(true) - $startedNs) / 1_000_000);
    }
}
