<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use App\Enums\RecommendedAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_id',
        'server_id',
        'sql_index_id',
        'action_type',
        'status',
        'scheduled_for',
        'started_at',
        'sql_script',
        'executed_at',
        'duration_seconds',
        'error_message',
        'initiated_by_contact_id',
    ];

    protected $casts = [
        'action_type' => RecommendedAction::class,
        'status' => MaintenanceStatus::class,
        'scheduled_for' => 'datetime',
        'started_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    // ============ RELACIONES ============

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function sqlIndex(): BelongsTo
    {
        return $this->belongsTo(SqlIndex::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'initiated_by_contact_id');
    }

    // ============ SCOPES ============

    public function scopePending($query)
    {
        return $query->where('status', MaintenanceStatus::PENDING);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', MaintenanceStatus::SCHEDULED);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', MaintenanceStatus::RUNNING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', MaintenanceStatus::COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', MaintenanceStatus::FAILED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', MaintenanceStatus::CANCELLED);
    }

    public function scopeReadyToExecute($query)
    {
        return $query->whereIn('status', [MaintenanceStatus::PENDING, MaintenanceStatus::SCHEDULED])
                     ->where(function($q) {
                         $q->whereNull('scheduled_for')
                           ->orWhere('scheduled_for', '<=', now());
                     });
    }

    public function scopeByServer($query, Server $server)
    {
        return $query->where('server_id', $server->id);
    }

    // ============ MÉTODOS DE UTILIDAD ============

    public function isPending(): bool
    {
        return $this->status === MaintenanceStatus::PENDING;
    }

    public function isScheduled(): bool
    {
        return $this->status === MaintenanceStatus::SCHEDULED;
    }

    public function isRunning(): bool
    {
        return $this->status === MaintenanceStatus::RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === MaintenanceStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === MaintenanceStatus::FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === MaintenanceStatus::CANCELLED;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            MaintenanceStatus::COMPLETED,
            MaintenanceStatus::FAILED,
            MaintenanceStatus::CANCELLED,
        ]);
    }

    public function isExecutable(): bool
    {
        return ($this->isPending() || $this->isScheduled()) && $this->isReady();
    }

    public function isReady(): bool
    {
        return $this->scheduled_for === null || $this->scheduled_for <= now();
    }

    public function canBeStarted(): bool
    {
        return ($this->isPending() || $this->isScheduled()) && 
               $this->isReady() && 
               $this->status !== MaintenanceStatus::RUNNING;
    }

    public function canBeCancelled(): bool
    {
        return !$this->isFinished() && 
               $this->status !== MaintenanceStatus::RUNNING;
    }

    public function start(): void
    {
        $this->update([
            'status' => MaintenanceStatus::RUNNING,
            'started_at' => now(),
        ]);
    }

    public function complete(?int $durationSeconds = null): void
    {
        $this->update([
            'status' => MaintenanceStatus::COMPLETED,
            'executed_at' => now(),
            'duration_seconds' => $durationSeconds ?? $this->duration_seconds,
        ]);
    }

    public function fail(string $errorMessage): void
    {
        $this->update([
            'status' => MaintenanceStatus::FAILED,
            'error_message' => $errorMessage,
            'executed_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => MaintenanceStatus::CANCELLED,
        ]);
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            MaintenanceStatus::PENDING => 'Pendiente',
            MaintenanceStatus::SCHEDULED => 'Programada',
            MaintenanceStatus::RUNNING => 'Ejecutando',
            MaintenanceStatus::COMPLETED => 'Completada',
            MaintenanceStatus::FAILED => 'Falló',
            MaintenanceStatus::CANCELLED => 'Cancelada',
            default => 'Desconocido',
        };
    }

    public function getActionTypeLabel(): string
    {
        return match($this->action_type) {
            RecommendedAction::REBUILD => 'REBUILD',
            RecommendedAction::REORGANIZE => 'REORGANIZE',
            RecommendedAction::UPDATE_STATISTICS => 'UPDATE STATISTICS',
            RecommendedAction::CREATE_INDEX => 'CREATE INDEX',
            RecommendedAction::DISABLE_INDEX => 'DISABLE INDEX',
            RecommendedAction::DROP_INDEX => 'DROP INDEX',
            RecommendedAction::CREATE_CLUSTERED => 'CREATE CLUSTERED',
            RecommendedAction::REVIEW => 'REVIEW',
            RecommendedAction::IGNORE => 'IGNORE',
            default => 'Desconocido',
        };
    }

    public function getDurationHuman(): string
    {
        if (!$this->duration_seconds) {
            return 'N/A';
        }

        $seconds = $this->duration_seconds;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        if ($secs > 0) $parts[] = "{$secs}s";

        return implode(' ', $parts) ?: '0s';
    }

    public function getTargetDisplay(): string
    {
        if ($this->sql_index_id) {
            return $this->sqlIndex?->qualifiedName() ?? "Índice #{$this->sql_index_id}";
        }

        if ($this->server_id) {
            return $this->server?->name ?? "Servidor #{$this->server_id}";
        }

        return 'Desconocido';
    }

    public function getActionForAlert(): ?array
    {
        // Método útil para el Integrante 4 al procesar acciones
        if (!$this->sql_script || $this->isFinished()) {
            return null;
        }

        return [
            'action_type' => $this->action_type->value,
            'sql_script' => $this->sql_script,
            'target' => $this->getTargetDisplay(),
        ];
    }
}