<?php

namespace App\Models;

use App\Enums\MissingIndexStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissingIndex extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id', 'schema_name', 'table_name', 'object_id', 'index_group_handle',
        'equality_columns', 'inequality_columns', 'included_columns',
        'estimated_impact', 'user_seeks', 'user_scans', 'fingerprint',
        'status', 'last_seen_at',
    ];

    protected $casts = [
        'equality_columns' => 'array',
        'inequality_columns' => 'array',
        'included_columns' => 'array',
        'estimated_impact' => 'decimal:2',
        'status' => MissingIndexStatus::class,
        'last_seen_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public static function makeFingerprint(string $tableName, array $equalityColumns, array $inequalityColumns, array $includedColumns): string
    {
        sort($equalityColumns);
        sort($inequalityColumns);
        sort($includedColumns);

        return hash('sha256', $tableName . '|' . implode(',', $equalityColumns) . '|' . implode(',', $inequalityColumns) . '|' . implode(',', $includedColumns));
    }
}