<?php

namespace App\Models;

use App\Enums\IndexRecordStatus;
use App\Enums\IndexType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SqlIndex extends Model
{
    use HasFactory;

    protected $table = 'sql_indexes';

    protected $fillable = [
        'server_id',              // ← AGREGADO
        'schema_name',
        'table_name',
        'index_name',
        'object_id',
        'index_id_native',
        'type',
        'is_unique',
        'is_primary_key',
        'is_disabled',
        'status',
        'fragmentation_percent',
        'size_mb',
        'page_count',
        'fill_factor',
        'optimal_fill_factor',
        'fill_factor_reason',
        'user_seeks',
        'user_scans',
        'user_lookups',
        'user_updates',
        'last_user_seek_at',
        'last_user_scan_at',
        'last_user_lookup_at',
        'usage_stats_since',
        'last_checked_at',
    ];

    protected $casts = [
        'type' => IndexType::class,
        'status' => IndexRecordStatus::class,
        'is_unique' => 'boolean',
        'is_primary_key' => 'boolean',
        'is_disabled' => 'boolean',
        'object_id' => 'integer',
        'index_id_native' => 'integer',
        'fragmentation_percent' => 'decimal:2',
        'size_mb' => 'decimal:2',
        'page_count' => 'integer',
        'fill_factor' => 'integer',
        'optimal_fill_factor' => 'integer',
        'user_seeks' => 'integer',
        'user_scans' => 'integer',
        'user_lookups' => 'integer',
        'user_updates' => 'integer',
        'last_user_seek_at' => 'datetime',
        'last_user_scan_at' => 'datetime',
        'last_user_lookup_at' => 'datetime',
        'usage_stats_since' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    protected $attributes = [
        'schema_name' => 'dbo',
        'type' => 'NONCLUSTERED',
        'is_unique' => false,
        'is_primary_key' => false,
        'is_disabled' => false,
        'status' => 'active',
        'user_seeks' => 0,
        'user_scans' => 0,
        'user_lookups' => 0,
        'user_updates' => 0,
    ];

    // ============ RELACIONES ============

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(IndexSnapshot::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function maintenanceActions(): HasMany
    {
        return $this->hasMany(MaintenanceAction::class);
    }

    public function operationalSnapshots(): HasMany
    {
        return $this->hasMany(IndexOperationalSnapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(IndexSnapshot::class)->latestOfMany('scanned_at');
    }

    public function latestOperationalSnapshot(): HasOne
    {
        return $this->hasOne(IndexOperationalSnapshot::class)->latestOfMany('sampled_at');
    }

    // ============ SCOPES ============

    public function scopeActive($query)
    {
        return $query->where('status', IndexRecordStatus::Active);
    }

    public function scopeDropped($query)
    {
        return $query->where('status', IndexRecordStatus::Dropped);
    }

    public function scopeFragmented($query, $threshold = 30)
    {
        return $query->where('fragmentation_percent', '>=', $threshold);
    }

    public function scopeUnused($query, int $minObservationDays = 30)
    {
        return $query->where('user_seeks', 0)
            ->where('user_scans', 0)
            ->where('user_lookups', 0)
            ->where(function ($q) use ($minObservationDays) {
                $q->whereNull('usage_stats_since')
                    ->orWhere('usage_stats_since', '<=', now()->subDays($minObservationDays));
            });
    }

    // ============ MÉTODOS DE UTILIDAD ============

    public function qualifiedName(): string
    {
        return "{$this->schema_name}.{$this->table_name}.{$this->index_name}";
    }

    public function isActive(): bool
    {
        return $this->status === IndexRecordStatus::Active;
    }

    public function isDropped(): bool
    {
        return $this->status === IndexRecordStatus::Dropped;
    }

    public function isFragmented($threshold = 30): bool
    {
        return $this->fragmentation_percent !== null &&
               $this->fragmentation_percent >= $threshold;
    }

    public function isCriticalFragmented($threshold = 50): bool
    {
        return $this->fragmentation_percent !== null &&
               $this->fragmentation_percent >= $threshold;
    }

    public function isUnused(int $minObservationDays = 30): bool
    {
        return $this->user_seeks === 0 &&
               $this->user_scans === 0 &&
               $this->user_lookups === 0 &&
               ! $this->usageStatsAreRecent($minObservationDays);
    }

    public function hasUsageStats(): bool
    {
        return $this->user_seeks > 0 ||
               $this->user_scans > 0 ||
               $this->user_lookups > 0;
    }

    public function usageStatsAreRecent(int $minimumObservationDays): bool
    {
        return $this->usage_stats_since !== null &&
               $this->usage_stats_since->diffInDays(now()) < $minimumObservationDays;
    }

    public function getTotalReads(): int
    {
        return $this->user_seeks + $this->user_scans + $this->user_lookups;
    }

    public function getTotalWrites(): int
    {
        return $this->user_updates;
    }

    public function getReadWriteRatio(): float
    {
        $reads = $this->getTotalReads();
        $writes = $this->getTotalWrites();

        if ($reads === 0 && $writes === 0) {
            return 0.0;
        }

        if ($writes === 0) {
            return $reads > 0 ? 100.0 : 0.0;
        }

        return $reads / $writes;
    }

    public function getDisplayName(): string
    {
        $type = $this->is_primary_key ? 'PK' : ($this->is_unique ? 'UNIQUE' : '');
        $status = $this->is_disabled ? ' [DISABLED]' : '';

        return $this->qualifiedName().$status.($type ? " ({$type})" : '');
    }
}
