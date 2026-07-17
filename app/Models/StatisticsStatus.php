<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatisticsStatus extends Model
{
    use HasFactory;

    protected $table = 'statistics_status';

    protected $fillable = [
        'server_id', 'schema_name', 'table_name', 'object_id', 'stats_id',
        'stats_name', 'row_count', 'modification_count', 'modification_ratio',
        'last_updated_at', 'scanned_at',
    ];

    protected $casts = [
        'modification_ratio' => 'decimal:4',
        'last_updated_at' => 'datetime',
        'scanned_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function isStale(float $threshold): bool
    {
        return $this->modification_ratio !== null && (float) $this->modification_ratio >= $threshold;
    }
}