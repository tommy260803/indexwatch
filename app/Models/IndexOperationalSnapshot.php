<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndexOperationalSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'sql_index_id',
        'server_scan_run_id',
        'leaf_page_split_count',
        'nonleaf_page_split_count',
        'page_split_count',
        'page_split_delta',
        'elapsed_seconds',
        'page_splits_per_minute',
        'sampled_at',
    ];

    protected $casts = [
        'leaf_page_split_count' => 'integer',
        'nonleaf_page_split_count' => 'integer',
        'page_split_count' => 'integer',
        'page_split_delta' => 'integer',
        'elapsed_seconds' => 'integer',
        'page_splits_per_minute' => 'decimal:4',
        'sampled_at' => 'datetime',
    ];

    public function sqlIndex(): BelongsTo
    {
        return $this->belongsTo(SqlIndex::class);
    }
}
