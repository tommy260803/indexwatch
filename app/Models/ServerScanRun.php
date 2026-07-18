<?php

namespace App\Models;

use App\Enums\ScanRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerScanRun extends Model
{
    protected $fillable = [
        'server_id',
        'correlation_id',
        'status',
        'capabilities',
        'metrics',
        'warnings',
        'error',
        'started_at',
        'finished_at',
        'duration_ms',
    ];

    protected $casts = [
        'status' => ScanRunStatus::class,
        'capabilities' => 'array',
        'metrics' => 'array',
        'warnings' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
