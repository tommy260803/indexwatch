<?php

return [
    'scan' => [
        'schedule' => env('INDEXWATCH_SCAN_SCHEDULE', '0 * * * *'),
        'statement_timeout' => (int) env('INDEXWATCH_STATEMENT_TIMEOUT', 60),
    ],

    'health_score' => [
        'version' => '1.0',
        'critical_index_penalty' => 10,
        'critical_index_cap' => 50,
        'stale_statistics_penalty' => 5,
        'stale_statistics_cap' => 25,
        'sustained_page_splits_penalty' => 5,
        'sustained_page_splits_cap' => 10,
        'heap_penalty' => 15,
        'heap_cap' => 30,
    ],

    'page_splits' => [
        'warning_per_minute' => (float) env('INDEXWATCH_PAGE_SPLIT_WARNING_PER_MINUTE', 100),
    ],

    'analytics' => [
        'unused_index_min_days' => (int) env('INDEXWATCH_UNUSED_INDEX_MIN_DAYS', 30),
        'unused_index_min_writes' => (int) env('INDEXWATCH_UNUSED_INDEX_MIN_WRITES', 100),
        'heap_min_size_mb' => (int) env('INDEXWATCH_HEAP_MIN_SIZE_MB', 100),
        'heap_min_activity' => (int) env('INDEXWATCH_HEAP_MIN_ACTIVITY', 1000),
        'missing_index_min_impact' => (float) env('INDEXWATCH_MISSING_INDEX_MIN_IMPACT', 100.0),
        'missing_index_min_ops' => (int) env('INDEXWATCH_MISSING_INDEX_MIN_OPS', 10),
        'duplicate_index_common_prefix_min' => (int) env('INDEXWATCH_DUPLICATE_PREFIX_MIN', 2),
    ],

    'reports' => [
        'storage_disk' => 'local',
        'storage_path' => 'private/reports',
        'expiration_days' => 7,
        'max_audit_logs' => 500,
    ],

    'maintenance' => [
        'lock_store' => env('INDEXWATCH_LOCK_STORE', 'redis'),
    ],
];