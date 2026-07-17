<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndexSnapshot extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'sql_index_id', 'fragmentation_percent', 'size_mb', 'page_count',
        'record_count', 'seeks', 'scans', 'lookups', 'writes', 'fill_factor',
        'index_last_used_at', 'scanned_at',
    ];

    protected $casts = [
        'fragmentation_percent' => 'decimal:2',
        'size_mb' => 'decimal:2',
        'fill_factor' => 'integer',
        'index_last_used_at' => 'datetime',
        'scanned_at' => 'datetime',
    ];

    public function isUnused(): bool
    {
        return $this->seeks === 0 && $this->scans === 0 && $this->lookups === 0;
    }

    public function sqlIndex(): BelongsTo
    {
        return $this->belongsTo(SqlIndex::class);
    }
}