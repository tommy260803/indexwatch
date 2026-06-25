<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Index extends Model
{
    protected $table = 'indexes';

    protected $fillable = [
        'server_id',
        'table_name',
        'index_name',
        'fragmentation_percent',
        'size_mb',
        'seeks',
        'scans',
        'lookups',
        'last_used_at',
    ];

    protected $casts = [
        'fragmentation_percent' => 'decimal:2',
        'last_used_at'          => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}