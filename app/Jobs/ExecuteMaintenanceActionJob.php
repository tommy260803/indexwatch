<?php

namespace App\Jobs;

use App\Enums\MaintenanceStatus;
use App\Models\AuditLog;
use App\Models\MaintenanceAction;
use App\Services\SqlServer\SqlServerConnectionFactory;
use App\Services\SqlServer\SqlServerErrorSanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExecuteMaintenanceActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $actionId,
        public readonly int $attempt = 0,
    ) {}

    public function handle(
        SqlServerConnectionFactory $connections,
        SqlServerErrorSanitizer $errors,
    ): void {
        $action = MaintenanceAction::with(['server', 'sqlIndex'])->find($this->actionId);

        if (! $action) {
            Log::warning('ExecuteMaintenanceActionJob: Action not found', ['action_id' => $this->actionId]);
            return;
        }

        if ($action->status !== MaintenanceStatus::Pending && $action->status !== MaintenanceStatus::Scheduled) {
            Log::info('ExecuteMaintenanceActionJob: Action not executable', [
                'action_id' => $action->id,
                'status' => $action->status?->value,
            ]);
            return;
        }

        $server = $action->server;
        if (! $server || !$server->isActive()) {
            $this->fail($action, 'Servidor inactivo o no encontrado', $errors);
            return;
        }

        $sql = $action->sql_script;
        if (empty($sql)) {
            $this->fail($action, 'Script SQL vacío', $errors);
            return;
        }

        $lockKey = "maintenance:server:{$server->id}:action:{$action->id}";
        $lock = app('cache')->store('redis')->lock($lockKey, 300);

        if (! $lock->get()) {
            Log::warning('ExecuteMaintenanceActionJob: Could not acquire lock', [
                'action_id' => $action->id,
                'attempt' => $this->attempt,
            ]);

            if ($this->attempt >= 3) {
                $this->fail($action, 'No se pudo adquirir el bloqueo después de 3 intentos', $errors);
                return;
            }

            static::dispatch($this->actionId, $this->attempt + 1)->delay(now()->addMinutes(5));
            return;
        }

        try {
            $action->forceFill([
                'status' => MaintenanceStatus::Running,
                'started_at' => now(),
            ])->save();

            AuditLog::create([
                'server_id' => $server->id,
                'auditable_type' => MaintenanceAction::class,
                'auditable_id' => $action->id,
                'actor_type' => 'user',
                'actor_name' => auth()->user()?->name ?? 'Sistema',
                'source' => 'job',
                'action' => 'maintenance_execute',
                'status' => 'started',
                'description' => 'Ejecutando acción: '.$action->action_type->value,
                'payload' => ['maintenance_action_id' => $action->id, 'sql' => $sql],
            ]);

            $connection = $connections->connect($server);
            $started = microtime(true);

            $connection->statement($sql);

            $durationMs = (int) round((microtime(true) - $started) * 1000);

            $action->forceFill([
                'status' => MaintenanceStatus::Completed,
                'executed_at' => now(),
                'duration_seconds' => (int) round($durationMs / 1000),
            ])->save();

            AuditLog::create([
                'server_id' => $server->id,
                'auditable_type' => MaintenanceAction::class,
                'auditable_id' => $action->id,
                'actor_type' => 'user',
                'actor_name' => auth()->user()?->name ?? 'Sistema',
                'source' => 'job',
                'action' => 'maintenance_result',
                'status' => 'succeeded',
                'description' => 'Acción completada en '.$durationMs.'ms',
                'payload' => ['maintenance_action_id' => $action->id, 'duration_ms' => $durationMs],
            ]);

            ScanServerJob::dispatch($server->id)->delay(now()->addMinutes(2));

            Log::info('ExecuteMaintenanceActionJob: Success', [
                'action_id' => $action->id,
                'duration_ms' => $durationMs,
            ]);

        } catch (Throwable $e) {
            $safeError = $errors->sanitize($e, $server);
            $durationMs = $action->started_at ? (int) round($action->started_at->diffInMilliseconds(now())) : 0;

            $action->forceFill([
                'status' => MaintenanceStatus::Failed,
                'error_message' => $safeError,
                'duration_seconds' => (int) round($durationMs / 1000),
            ])->save();

            AuditLog::create([
                'server_id' => $server->id,
                'auditable_type' => MaintenanceAction::class,
                'auditable_id' => $action->id,
                'actor_type' => 'user',
                'actor_name' => auth()->user()?->name ?? 'Sistema',
                'source' => 'job',
                'action' => 'maintenance_result',
                'status' => 'failed',
                'description' => 'Acción falló: '.$safeError,
                'payload' => ['maintenance_action_id' => $action->id, 'error' => $safeError],
            ]);

            Log::error('ExecuteMaintenanceActionJob: Failed', [
                'action_id' => $action->id,
                'error' => $safeError,
            ]);

        } finally {
            $connections->disconnect($server);
            $lock->release();
        }
    }

    private function fail(MaintenanceAction $action, string $reason, SqlServerErrorSanitizer $errors): void
    {
        $action->forceFill([
            'status' => MaintenanceStatus::Failed,
            'error_message' => $reason,
        ])->save();

        AuditLog::create([
            'server_id' => $action->server_id,
            'auditable_type' => MaintenanceAction::class,
            'auditable_id' => $action->id,
            'actor_type' => 'user',
            'actor_name' => auth()->user()?->name ?? 'Sistema',
            'source' => 'job',
            'action' => 'maintenance_result',
            'status' => 'failed',
            'description' => $reason,
            'payload' => ['maintenance_action_id' => $action->id],
        ]);

        Log::error('ExecuteMaintenanceActionJob: Failed early', [
            'action_id' => $action->id,
            'reason' => $reason,
        ]);
    }
}
