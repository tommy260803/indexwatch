<?php

namespace App\Models;

use App\Enums\AuditActorType;
use App\Enums\AuditSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'server_id',
        'auditable_type',
        'auditable_id',
        'action',
        'actor_type',
        'actor_identifier',
        'actor_name',
        'source',
        'status',
        'description',
        'payload',
    ];

    protected $casts = [
        'actor_type' => AuditActorType::class,
        'source' => AuditSource::class,
        'payload' => 'array',
        'actor_identifier' => 'string',
    ];

    protected $attributes = [
        'payload' => '{}',
    ];

    // ============ RELACIONES ============

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    // ============ SCOPES ============

    public function scopeByActorType($query, AuditActorType $type)
    {
        return $query->where('actor_type', $type);
    }

    public function scopeBySource($query, AuditSource $source)
    {
        return $query->where('source', $source);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeForAuditable($query, Model $auditable)
    {
        return $query->where('auditable_type', $auditable::class)
                     ->where('auditable_id', $auditable->getKey());
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeForServer($query, Server $server)
    {
        return $query->where('server_id', $server->id);
    }

    // ============ MÉTODOS DE UTILIDAD ============

    public static function record(
        Model $auditable,
        string $action,
        AuditActorType $actorType,
        ?string $actorIdentifier = null,
        array $payload = [],
        ?int $serverId = null,
        ?AuditSource $source = null
    ): self {
        return static::create([
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'action' => $action,
            'actor_type' => $actorType,
            'actor_identifier' => $actorIdentifier,
            'payload' => $payload,
            'server_id' => $serverId,
            'source' => $source,
        ]);
    }

    public function isFromWhatsApp(): bool
    {
        return $this->actor_type === AuditActorType::WHATSAPP;
    }

    public function isFromSystem(): bool
    {
        return $this->actor_type === AuditActorType::SYSTEM;
    }

    public function isFromUser(): bool
    {
        return $this->actor_type === AuditActorType::USER;
    }

    public function getActorDisplayName(): string
    {
        return match($this->actor_type) {
            AuditActorType::WHATSAPP => "WhatsApp: {$this->actor_identifier}",
            AuditActorType::SYSTEM => "Sistema",
            AuditActorType::USER => "Usuario: {$this->actor_identifier}",
            AuditActorType::API => "API: {$this->actor_identifier}",
            default => "Desconocido",
        };
    }

    public function getSourceLabel(): string
    {
        return match($this->source) {
            AuditSource::WEBHOOK => 'Webhook WhatsApp',
            AuditSource::CLI => 'Comando CLI',
            AuditSource::DASHBOARD => 'Dashboard',
            AuditSource::SCHEDULER => 'Scheduler',
            AuditSource::JOB => 'Job',
            default => 'Desconocido',
        };
    }

    public function getActionLabel(): string
    {
        return match($this->action) {
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'executed' => 'Ejecutado',
            'failed' => 'Falló',
            'scheduled' => 'Programado',
            'cancelled' => 'Cancelado',
            'created' => 'Creado',
            'updated' => 'Actualizado',
            'deleted' => 'Eliminado',
            'scanned' => 'Escaneado',
            default => $this->action,
        };
    }

    public function getAuditableDisplay(): string
    {
        $type = class_basename($this->auditable_type);
        $id = $this->auditable_id;
        
        // Si el auditable está cargado, usar su representación
        if ($this->relationLoaded('auditable') && $this->auditable) {
            if (method_exists($this->auditable, 'getDisplayName')) {
                return $this->auditable->getDisplayName();
            }
            if (method_exists($this->auditable, 'qualifiedName')) {
                return $this->auditable->qualifiedName();
            }
            return "{$type} #{$id}";
        }
        
        return "{$type} #{$id}";
    }
}