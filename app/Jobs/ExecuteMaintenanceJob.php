<?php

namespace App\Jobs;

use App\Enums\AlertStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\MaintenanceAction;
use App\Models\Server;
use App\Services\Maintenance\MaintenanceWindowResolver;
use App\Services\Maintenance\TsqlGeneratorService;
use App\Services\SqlServer\SqlServerConnectionFactory;
use App\Services\SqlServer\SqlServerErrorSanitizer;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExecuteMaintenanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $alertId,
        public readonly int $attempt = 0,
    ) {}

    public function handle(
        TsqlGeneratorService $tsqlGenerator,
        MaintenanceWindowResolver $windowResolver,
        SqlServerConnectionFactory $connections,
        SqlServerErrorSanitizer $errors,
        WhatsAppService $whatsapp,
    ): void {
        $alert = Alert::with(['server', 'sqlIndex', 'approvedBy'])->find($this->alertId);

        if (! $alert) {
            Log::warning('ExecuteMaintenanceJob: Alert not found', ['alert_id' => $this->alertId]);
            return;
        }

        if (! $alert->canBeExecuted()) {
            Log::info('ExecuteMaintenanceJob: Alert cannot be executed', [
                'alert_id' => $alert->id,
                'status' => $alert->status?->value,
            ]);
            return;
        }

        // Acquire lock to prevent concurrent execution
        $lockKey = "maintenance:server:{$alert->server_id}:action:{$alert->id}";
        $lockStore = config('indexwatch.maintenance.lock_store', 'redis');
        $lock = app('cache')->store($lockStore)->lock($lockKey, 300); // 5 min lock

        if (! $lock->get()) {
            Log::warning('ExecuteMaintenanceJob: Could not acquire lock', ['alert_id' => $alert->id, 'attempt' => $this->attempt]);
            // Prevent infinite re-queuing - max 3 attempts
            if ($this->attempt >= 3) {
                Log::warning('ExecuteMaintenanceJob: Max lock acquisition attempts reached, marking as failed', ['alert_id' => $alert->id]);
                $contact = $alert->approvedBy;
                $this->fail($alert, 'Max lock acquisition attempts reached', $whatsapp, $contact);
                return;
            }
            // In sync queue, release() returns null, so we need to re-dispatch with delay
            if ($this->release()) {
                $this->release()->delay(now()->addMinutes(5));
            } else {
                static::dispatch($this->alertId, $this->attempt + 1)->delay(now()->addMinutes(5));
            }
            return;
        }

        try {
            $this->execute($alert, $tsqlGenerator, $windowResolver, $connections, $errors, $whatsapp, $lock);
        } finally {
            $lock->release();
        }
    }

    private function execute(
        Alert $alert,
        TsqlGeneratorService $tsqlGenerator,
        MaintenanceWindowResolver $windowResolver,
        SqlServerConnectionFactory $connections,
        SqlServerErrorSanitizer $errors,
        WhatsAppService $whatsapp,
        \Illuminate\Contracts\Cache\Lock $lock,
    ): void {
        $server = $alert->server;
        $contact = $alert->approvedBy;

        // Re-validate preconditions
        if (! $server || ! $server->isActive()) {
            $this->fail($alert, 'Server inactive or not found', $whatsapp, $contact);
            return;
        }

        // Check maintenance window if scheduled
        if ($alert->scheduled_for && ! $windowResolver->isWithinWindow($server)) {
            Log::info('ExecuteMaintenanceJob: Outside maintenance window, re-queueing', ['alert_id' => $alert->id, 'attempt' => $this->attempt]);
            // Prevent infinite re-queuing - max 3 attempts
            if ($this->attempt >= 3) {
                Log::warning('ExecuteMaintenanceJob: Max re-queue attempts reached, marking as failed', ['alert_id' => $alert->id]);
                $this->fail($alert, 'Max re-queue attempts reached (outside maintenance window)', $whatsapp, $contact);
                return;
            }
            // In sync queue, release() returns null, so we need to re-dispatch with delay
            if ($this->release()) {
                $this->release()->delay($alert->scheduled_for);
            } else {
                static::dispatch($this->alertId, $this->attempt + 1)->delay($alert->scheduled_for);
            }
            return;
        }

        $sql = $tsqlGenerator->generate($alert);

        // Create maintenance action record
        $action = MaintenanceAction::create([
            'alert_id'           => $alert->id,
            'server_id'          => $server->id,
            'action_type'        => $alert->recommended_action,
            'status'             => MaintenanceStatus::Running,
            'sql_preview'        => $sql,
            'scheduled_for'      => $alert->scheduled_for,
            'started_at'         => now(),
            'metadata'           => $alert->metadata ?? [],
        ]);

        // Audit: execution started
        AuditLog::create([
            'server_id'                 => $server->id,
            'alert_id'                  => $alert->id,
            'maintenance_action_id'     => $action->id,
            'auditable_type'            => MaintenanceAction::class,
            'auditable_id'              => $action->id,
            'actor_type'                => 'system',
            'actor_name'                => 'ExecuteMaintenanceJob',
            'source'                    => 'job',
            'action'                    => 'maintenance_execute',
            'status'                    => 'started',
            'description'               => 'Started maintenance action: ' . $action->action_type->value,
            'metadata'                  => ['sql' => $sql],
        ]);

        // Update alert status
        $alert->forceFill([
            'status'      => AlertStatus::Running,
            'executed_at' => now(),
        ])->save();

        try {
            $connection = $connections->connect($server);
            $started = microtime(true);

            // Execute the T-SQL
            $connection->statement($sql);

$durationSeconds = (int) round((microtime(true) - $started));

            // Update action with success
            $action->forceFill([
                'status'       => MaintenanceStatus::Succeeded,
                'executed_at'  => now(),
                'duration_seconds'  => $durationSeconds,
                'result'       => 'Executed successfully',
            ])->save();

            // Update alert
            $alert->forceFill([
                'status'     => AlertStatus::Succeeded,
                'executed_at'=> now(),
                'metadata'   => array_merge($alert->metadata ?? [], [
                    'execution_result' => 'success',
                    'duration_ms'      => $durationMs,
                ]),
            ])->save();

            // Audit: success
            AuditLog::create([
                'server_id'                 => $server->id,
                'alert_id'                  => $alert->id,
                'maintenance_action_id'     => $action->id,
                'auditable_type'            => MaintenanceAction::class,
                'auditable_id'              => $action->id,
                'actor_type'                => 'system',
                'actor_name'                => 'ExecuteMaintenanceJob',
                'source'                    => 'job',
                'action'                    => 'maintenance_result',
                'status'                    => 'succeeded',
                'description'               => 'Maintenance action completed successfully',
                'metadata'                  => ['duration_ms' => $durationMs],
            ]);

            // Trigger verification scan after 2 minutes
            \App\Jobs\ScanServerJob::dispatch($server->id)->delay(now()->addMinutes(2));

            // Notify via WhatsApp
            if ($contact) {
                $whatsapp->sendConfirmation($contact->phone_e164, sprintf(
                    "✅ *Mantenimiento completado — IndexWatch*\n\n" .
                    "🔹 Índice: %s\n" .
                    "🔹 Acción: %s\n" .
                    "🔹 Duración: %d ms\n" .
                    "🔹 Hora: %s\n\n" .
                    "📜 Script ejecutado:\n%s",
                    $alert->getSubjectDisplay(),
                    $action->action_type->value,
                    $durationMs,
                    now()->format('H:i:s'),
                    $sql
                ));
            }

            Log::info('ExecuteMaintenanceJob: Success', [
                'alert_id' => $alert->id,
                'action_id' => $action->id,
                'duration_ms' => $durationMs,
            ]);

        } catch (Throwable $e) {
            $safeError = $errors->sanitize($e, $server);
            $durationSeconds = $action->started_at ? (int) round($action->started_at->diffInSeconds(now())) : 0;

            $action->forceFill([
                'status'       => MaintenanceStatus::Failed,
                'executed_at'  => now(),
                'duration_seconds'  => $durationSeconds,
                'error'        => $safeError,
            ])->save();

            $alert->forceFill([
                'status'     => AlertStatus::Failed,
                'metadata'   => array_merge($alert->metadata ?? [], [
                    'execution_result' => 'failed',
                    'error'            => $safeError,
                ]),
            ])->save();

            AuditLog::create([
                'server_id'                 => $server->id,
                'alert_id'                  => $alert->id,
                'maintenance_action_id'     => $action->id,
                'auditable_type'            => MaintenanceAction::class,
                'auditable_id'              => $action->id,
                'actor_type'                => 'system',
                'actor_name'                => 'ExecuteMaintenanceJob',
                'source'                    => 'job',
                'action'                    => 'maintenance_result',
                'status'                    => 'failed',
                'description'               => 'Maintenance action failed: ' . $safeError,
                'metadata'                  => ['error' => $safeError],
            ]);

            if ($contact) {
                $whatsapp->sendConfirmation($contact->phone_e164, sprintf(
                    "❌ *Mantenimiento falló — IndexWatch*\n\n" .
                    "🔹 Índice: %s\n" .
                    "🔹 Acción: %s\n" .
                    "🔹 Error: %s\n" .
                    "🔹 Hora: %s",
                    $alert->getSubjectDisplay(),
                    $action->action_type->value,
                    $safeError,
                    now()->format('H:i:s')
                ));
            }

            Log::error('ExecuteMaintenanceJob: Failed', [
                'alert_id' => $alert->id,
                'action_id' => $action->id,
                'error' => $safeError,
            ]);
        } finally {
            $connections->disconnect($server);
        }
    }

    private function fail(Alert $alert, string $reason, WhatsAppService $whatsapp, ?Contact $contact): void
    {
        $alert->forceFill([
            'status'     => AlertStatus::Failed,
            'metadata'   => array_merge($alert->metadata ?? [], ['execution_result' => 'failed', 'error' => $reason]),
        ])->save();

        AuditLog::create([
            'server_id'      => $alert->server_id,
            'alert_id'       => $alert->id,
            'auditable_type' => Alert::class,
            'auditable_id'   => $alert->id,
            'actor_type'     => 'system',
            'actor_name'     => 'ExecuteMaintenanceJob',
            'source'         => 'job',
            'action'         => 'maintenance_result',
            'status'         => 'failed',
            'description'    => $reason,
        ]);

        if ($contact) {
            $whatsapp->sendConfirmation($contact->phone_e164, sprintf(
                "❌ *Mantenimiento no ejecutado — IndexWatch*\n\n" .
                "🔹 Índice: %s\n" .
                "🔹 Razón: %s\n" .
                "🔹 Hora: %s",
                $alert->getSubjectDisplay(),
                $reason,
                now()->format('H:i:s')
            ));
        }
    }
}