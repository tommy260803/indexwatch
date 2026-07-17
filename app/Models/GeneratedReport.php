<?php

namespace App\Models;

use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'requested_by_user_id', 'server_id', 'filters', 'format',
        'status', 'file_path', 'expires_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'format' => ReportFormat::class,
        'status' => ReportStatus::class,
        'expires_at' => 'datetime',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}