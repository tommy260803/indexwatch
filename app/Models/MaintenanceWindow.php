<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceWindow extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id', 'day_of_week', 'start_time', 'end_time', 'timezone', 'active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'active' => 'boolean',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}