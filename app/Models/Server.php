<?php

namespace App\Models;

use App\Enums\ScanStatus;
use App\Enums\ServerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Server extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'host',
        'port',
        'database_name',
        'username',
        'password',
        'status',
        'connection_options',
        'warning_threshold',
        'critical_threshold',
        'health_score',
        'health_score_updated_at',
        'stats_stale_threshold',
        'minimum_index_pages',
        'timezone',
        'last_scanned_at',
        'last_scan_status',
        'last_scan_error',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'status' => ServerStatus::class,
        'port' => 'integer',
        'warning_threshold' => 'decimal:2',
        'critical_threshold' => 'decimal:2',
        'connection_options' => 'array',
        'health_score' => 'integer',
        'health_score_updated_at' => 'datetime',
        'stats_stale_threshold' => 'decimal:2',
        'minimum_index_pages' => 'integer',
        'last_scanned_at' => 'datetime',
        'last_scan_status' => ScanStatus::class,
    ];

    protected $attributes = [
        'port' => 1433,
        'status' => 'active',
        'warning_threshold' => 5.00,
        'critical_threshold' => 30.00,
        'stats_stale_threshold' => 20.00,
        'minimum_index_pages' => 1000,
        'timezone' => 'America/Lima',
    ];

    // ============ RELACIONES ============

    public function sqlIndexes(): HasMany
    {
        return $this->hasMany(SqlIndex::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'server_contact')->withTimestamps();
    }

    public function maintenanceWindows(): HasMany
    {
        return $this->hasMany(MaintenanceWindow::class);
    }

    public function maintenanceActions(): HasMany
    {
        return $this->hasMany(MaintenanceAction::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class);
    }

    // Relación a través de SqlIndex
    public function indexSnapshots(): HasManyThrough
    {
        return $this->hasManyThrough(
            IndexSnapshot::class,
            SqlIndex::class,
            'server_id',  // FK en SqlIndex
            'sql_index_id', // FK en IndexSnapshot
            'id',          // PK en Server
            'id'           // PK en SqlIndex
        );
    }

    // ============ SCOPES ============

    public function scopeActive($query)
    {
        return $query->where('status', ServerStatus::Active);
    }

    public function scopeHealthy($query, int $minScore = 70)
    {
        return $query->where('health_score', '>=', $minScore);
    }

    public function scopeWithRecentScan($query, int $hours = 24)
    {
        return $query->where('last_scanned_at', '>=', now()->subHours($hours));
    }

    // ============ MÉTODOS DE UTILIDAD ============

    public function isActive(): bool
    {
        return $this->status === ServerStatus::Active;
    }

    public function isHealthy(): bool
    {
        return $this->health_score !== null && $this->health_score >= 70;
    }

    public function needsAttention(): bool
    {
        return $this->health_score !== null && $this->health_score < 50;
    }

    public function hasRecentScan(): bool
    {
        return $this->last_scanned_at !== null && 
               $this->last_scanned_at->diffInHours(now()) < 24;
    }

    public function getQualifiedName(): string
    {
        return "{$this->name} ({$this->host}:{$this->port}/{$this->database_name})";
    }

    public function getConnectionString(): string
    {
        return "sqlsrv://{$this->username}:****@{$this->host}:{$this->port}/{$this->database_name}";
    }
}