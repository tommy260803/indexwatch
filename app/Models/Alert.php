<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\RecommendedAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'sql_index_id',
        'subject_type',
        'subject_id',
        'fingerprint',
        'alert_type',
        'severity',
        'status',
        'recommended_action',
        'fragmentation_percent',
        'metadata',
        'whatsapp_message_id',
        'responded_by_contact_id',
        'responded_action',
        'approved_by_contact_id',
        'approved_at',
        'scheduled_for',
        'executed_at',
        'resolved_at',  // ❌ ELIMINADO - no existe en migración
    ];

    protected $casts = [
        'alert_type' => AlertType::class,
        'severity' => AlertSeverity::class,
        'status' => AlertStatus::class,
        'recommended_action' => RecommendedAction::class,
        'responded_action' => RecommendedAction::class,
        'fragmentation_percent' => 'decimal:2',
        'metadata' => 'array',
        'approved_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'executed_at' => 'datetime',
        'resolved_at' => 'datetime',  // ❌ ELIMINADO
    ];

    // ============ FACTORY METHODS ============

    public static function makeFingerprint(
        int $serverId,
        AlertType $alertType,
        ?int $sqlIndexId = null,
        ?string $subjectType = null,
        ?int $subjectId = null
    ): string {
        $subjectKey = match (true) {
            $sqlIndexId !== null => "index:{$sqlIndexId}",
            $subjectType !== null && $subjectId !== null => "{$subjectType}:{$subjectId}",
            default => 'server',
        };

        return hash('sha256', "{$serverId}:{$alertType->value}:{$subjectKey}");
    }

    // ============ RELACIONES ============

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function sqlIndex(): BelongsTo
    {
        return $this->belongsTo(SqlIndex::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'responded_by_contact_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'approved_by_contact_id');
    }

    public function maintenanceActions(): HasMany
    {
        return $this->hasMany(MaintenanceAction::class);
    }

    // ============ SCOPES ============

    public function scopePending($query)
    {
        return $query->whereIn('status', [
            AlertStatus::PENDING,
            AlertStatus::SENT,
            AlertStatus::AWAITING_RESPONSE,
        ]);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            AlertStatus::PENDING,
            AlertStatus::SENT,
            AlertStatus::AWAITING_RESPONSE,
            AlertStatus::APPROVED,
            AlertStatus::SCHEDULED,
            AlertStatus::RUNNING,
        ]);
    }

    public function scopeClosed($query)
    {
        return $query->whereIn('status', [
            AlertStatus::SUCCEEDED,
            AlertStatus::FAILED,
            AlertStatus::EXPIRED,
            AlertStatus::DISMISSED,
        ]);
    }

    public function scopeByFingerprint($query, string $fingerprint)
    {
        return $query->where('fingerprint', $fingerprint);
    }

    public function scopeForServer($query, Server $server)
    {
        return $query->where('server_id', $server->id);
    }

    public function scopeForSqlIndex($query, SqlIndex $sqlIndex)
    {
        return $query->where('sql_index_id', $sqlIndex->id);
    }

    // ============ MÉTODOS DE UTILIDAD ============

    public function isOpen(): bool
    {
        return in_array($this->status, [
            AlertStatus::PENDING,
            AlertStatus::SENT,
            AlertStatus::AWAITING_RESPONSE,
            AlertStatus::APPROVED,
            AlertStatus::SCHEDULED,
            AlertStatus::RUNNING,
        ]);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [
            AlertStatus::SUCCEEDED,
            AlertStatus::FAILED,
            AlertStatus::EXPIRED,
            AlertStatus::DISMISSED,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === AlertStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === AlertStatus::APPROVED;
    }

    public function isScheduled(): bool
    {
        return $this->status === AlertStatus::SCHEDULED;
    }

    public function isRunning(): bool
    {
        return $this->status === AlertStatus::RUNNING;
    }

    public function isSucceeded(): bool
    {
        return $this->status === AlertStatus::SUCCEEDED;
    }

    public function isFailed(): bool
    {
        return $this->status === AlertStatus::FAILED;
    }

    public function isExpired(): bool
    {
        return $this->status === AlertStatus::EXPIRED;
    }

    public function isDismissed(): bool
    {
        return $this->status === AlertStatus::DISMISSED;
    }

    public function canBeApproved(): bool
    {
        return $this->isPending() || $this->status === AlertStatus::SENT;
    }

    public function canBeScheduled(): bool
    {
        return $this->isApproved() && $this->scheduled_for === null;
    }

    public function canBeExecuted(): bool
    {
        return $this->isApproved() || $this->isScheduled();
    }

    public function needsApproval(): bool
    {
        return $this->isPending() || $this->status === AlertStatus::SENT;
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            AlertStatus::PENDING => 'Pendiente',
            AlertStatus::SENT => 'Enviada',
            AlertStatus::AWAITING_RESPONSE => 'Esperando respuesta',
            AlertStatus::APPROVED => 'Aprobada',
            AlertStatus::SCHEDULED => 'Programada',
            AlertStatus::RUNNING => 'Ejecutando',
            AlertStatus::SUCCEEDED => 'Completada',
            AlertStatus::FAILED => 'Falló',
            AlertStatus::EXPIRED => 'Expirada',
            AlertStatus::DISMISSED => 'Descartada',
            default => 'Desconocido',
        };
    }

    public function getSeverityLabel(): string
    {
        return match($this->severity) {
            AlertSeverity::INFO => 'Información',
            AlertSeverity::WARNING => 'Advertencia',
            AlertSeverity::CRITICAL => 'Crítica',
            default => 'Desconocido',
        };
    }

    public function getSeverityColor(): string
    {
        return match($this->severity) {
            AlertSeverity::INFO => 'blue',
            AlertSeverity::WARNING => 'yellow',
            AlertSeverity::CRITICAL => 'red',
            default => 'gray',
        };
    }

    public function getTypeLabel(): string
    {
        return match($this->alert_type) {
            AlertType::FRAGMENTATION => 'Fragmentación',
            AlertType::INACTIVE => 'Índice inactivo',
            AlertType::MISSING_INDEX => 'Índice faltante',
            AlertType::DUPLICATE_INDEX => 'Índice duplicado',
            AlertType::HEAP => 'Heap (sin clúster)',
            AlertType::STALE_STATISTICS => 'Estadísticas obsoletas',
            AlertType::PAGE_SPLITS => 'Page splits excesivos',
            AlertType::FILL_FACTOR => 'Fill factor óptimo',
            default => 'Desconocido',
        };
    }

    public function getSubjectDisplay(): string
    {
        if ($this->sql_index_id) {
            return $this->sqlIndex?->qualifiedName() ?? "Índice #{$this->sql_index_id}";
        }

        if ($this->subject_type && $this->subject_id) {
            $type = class_basename($this->subject_type);
            return "{$type} #{$this->subject_id}";
        }

        return "Servidor #{$this->server_id}";
    }

    public function getActionHistory(): array
    {
        $history = [];

        if ($this->approved_by_contact_id && $this->approved_at) {
            $history[] = [
                'action' => 'approved',
                'by' => $this->approvedBy?->name ?? "Contacto #{$this->approved_by_contact_id}",
                'at' => $this->approved_at,
            ];
        }

        if ($this->scheduled_for) {
            $history[] = [
                'action' => 'scheduled',
                'at' => $this->scheduled_for,
            ];
        }

        if ($this->responded_by_contact_id && $this->responded_action) {
            $history[] = [
                'action' => 'responded',
                'response' => $this->responded_action->value,
                'by' => $this->respondedBy?->name ?? "Contacto #{$this->responded_by_contact_id}",
            ];
        }

        if ($this->executed_at) {
            $history[] = [
                'action' => $this->status === AlertStatus::SUCCEEDED ? 'succeeded' : 'failed',
                'at' => $this->executed_at,
            ];
        }

        return $history;
    }
}