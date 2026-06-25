<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'index_id',
        'type',
        'severity',
        'status',
        'whatsapp_message_id',
        'action_taken',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function index(): BelongsTo
    {
        return $this->belongsTo(Index::class);
    }
}