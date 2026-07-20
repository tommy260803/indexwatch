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
        'lock_ttl_seconds' => (int) env('INDEXWATCH_LOCK_TTL', 300),

        // Política ante contactos no autorizados que intentan aprobar desde WhatsApp.
        // 'reject' → responde con error; 'silent' → ignora sin respuesta.
        'unauthorized_policy' => env('INDEXWATCH_UNAUTHORIZED_POLICY', 'reject'),

        // Comportamiento cuando no hay ventanas de mantenimiento configuradas para un servidor.
        // 'pending' → queda aprobada sin programar (requiere ejecución manual).
        // 'immediate' → ejecuta inmediatamente si la acción es de riesgo none/medium.
        'no_window_behavior' => env('INDEXWATCH_NO_WINDOW_BEHAVIOR', 'pending'),

        // Máximo de reintentos de adquisición de lock antes de fallar el Job.
        'max_lock_attempts' => (int) env('INDEXWATCH_MAX_LOCK_ATTEMPTS', 3),

        // Timeout en segundos para la ejecución de T-SQL en SQL Server.
        'tsql_timeout_seconds' => (int) env('INDEXWATCH_TSQL_TIMEOUT', 120),

        // Acciones que requieren doble confirmación (el contacto debe aprobar dos veces).
        // Se evalúa contra RecommendedAction::requiresDoubleConfirmation().
        'require_double_confirmation' => filter_var(
            env('INDEXWATCH_REQUIRE_DOUBLE_CONFIRMATION', true),
            FILTER_VALIDATE_BOOL,
        ),

        // Idempotencia: horas que un evento de WhatsApp se retiene para evitar reprocesamiento.
        'webhook_event_ttl_hours' => (int) env('INDEXWATCH_WEBHOOK_EVENT_TTL_HOURS', 72),
    ],
];
