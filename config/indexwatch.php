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
    ],

    'page_splits' => [
        'warning_per_minute' => (float) env('INDEXWATCH_PAGE_SPLIT_WARNING_PER_MINUTE', 100),
    ],
];
