<?php

namespace App\Services\SqlServer;

use App\Domain\Analytics\DTO\MissingIndexMetric;
use App\Domain\Monitoring\DTO\IndexMetric;
use App\Domain\Monitoring\DTO\InspectionResult;
use App\Domain\Monitoring\DTO\PageSplitMetric;
use App\Domain\Monitoring\DTO\StatisticsMetric;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Throwable;

class SqlServerInspectorService
{
    // El inventario es la base del análisis: lista cada índice físico existente.
    // Sobre esa lista se cruzan fragmentación, uso, estadísticas y page splits.
    private const INVENTORY_QUERY = <<<'SQL'
        SET NOCOUNT ON;

        SELECT
            sch.name AS schema_name,
            tbl.name AS table_name,
            tbl.object_id AS object_id,
            idx.index_id AS index_id,
            idx.name AS index_name,
            idx.type_desc AS index_type,
            idx.is_unique,
            idx.is_primary_key,
            idx.is_disabled,
            idx.fill_factor
        FROM sys.tables AS tbl
        INNER JOIN sys.schemas AS sch ON sch.schema_id = tbl.schema_id
        INNER JOIN sys.indexes AS idx ON idx.object_id = tbl.object_id
        WHERE tbl.is_ms_shipped = 0
          AND idx.type IN (1, 2, 5, 6)
          AND idx.is_hypothetical = 0
          AND idx.name IS NOT NULL;
        SQL;

    // Fragmentación física: esta métrica ayuda a decidir si un índice necesita
    // reorganización o rebuild, según el nivel de degradación.
    private const FRAGMENTATION_QUERY = <<<'SQL'
        SET NOCOUNT ON;

        WITH physical AS (
            SELECT
                ips.object_id,
                ips.index_id,
                SUM(CONVERT(bigint, ips.page_count)) AS page_count,
                CONVERT(decimal(9, 4),
                    SUM(CONVERT(float, ips.avg_fragmentation_in_percent) * CONVERT(float, ips.page_count))
                    / NULLIF(SUM(CONVERT(float, ips.page_count)), 0.0)
                ) AS fragmentation_percent
            FROM sys.dm_db_index_physical_stats(DB_ID(), NULL, NULL, NULL, N'LIMITED') AS ips
            INNER JOIN sys.tables AS tbl ON tbl.object_id = ips.object_id
            INNER JOIN sys.indexes AS idx
                ON idx.object_id = ips.object_id AND idx.index_id = ips.index_id
            WHERE tbl.is_ms_shipped = 0
              AND idx.type IN (1, 2)
              AND idx.is_hypothetical = 0
              AND idx.is_disabled = 0
              AND ips.alloc_unit_type_desc = N'IN_ROW_DATA'
              AND ips.index_level = 0
            GROUP BY ips.object_id, ips.index_id
            HAVING SUM(CONVERT(bigint, ips.page_count)) >= ?
        )
        SELECT
            object_id,
            index_id,
            page_count,
            CONVERT(decimal(19, 2), CONVERT(decimal(38, 4), page_count) * 8.0 / 1024.0) AS size_mb,
            fragmentation_percent
        FROM physical
        OPTION (MAXDOP 1);
        SQL;

    // Las DMVs de uso muestran cuántas veces se consulta o modifica cada índice.
    // Con eso se detectan índices poco útiles y se calcula fill factor sugerido.
    private const USAGE_QUERY = <<<'SQL'
        SET NOCOUNT ON;

        SELECT
            tbl.object_id,
            idx.index_id,
            COALESCE(us.user_seeks, 0) AS user_seeks,
            COALESCE(us.user_scans, 0) AS user_scans,
            COALESCE(us.user_lookups, 0) AS user_lookups,
            COALESCE(us.user_updates, 0) AS user_updates,
            us.last_user_seek AS last_user_seek_at,
            us.last_user_scan AS last_user_scan_at,
            us.last_user_lookup AS last_user_lookup_at,
            osi.sqlserver_start_time
        FROM sys.tables AS tbl
        INNER JOIN sys.indexes AS idx ON idx.object_id = tbl.object_id
        LEFT JOIN sys.dm_db_index_usage_stats AS us
            ON us.database_id = DB_ID()
            AND us.object_id = idx.object_id
            AND us.index_id = idx.index_id
        CROSS JOIN sys.dm_os_sys_info AS osi
        WHERE tbl.is_ms_shipped = 0
          AND idx.type IN (1, 2, 5, 6)
          AND idx.is_hypothetical = 0;
        SQL;

    // Las estadísticas obsoletas hacen que el optimizador elija planes malos.
    // Esta consulta ayuda a detectar cuándo toca actualizar estadísticas.
    private const STATISTICS_QUERY = <<<'SQL'
        SET NOCOUNT ON;

        SELECT
            tbl.object_id,
            sch.name AS schema_name,
            tbl.name AS table_name,
            st.stats_id,
            st.name AS stats_name,
            props.last_updated AS last_updated_at,
            COALESCE(props.rows, 0) AS row_count,
            COALESCE(props.modification_counter, 0) AS modification_count,
            CONVERT(decimal(19, 4), CASE
                WHEN props.rows > 0
                THEN 100.0 * CONVERT(decimal(38, 10), props.modification_counter)
                    / CONVERT(decimal(38, 10), props.rows)
                ELSE NULL
            END) AS modification_percent
        FROM sys.tables AS tbl
        INNER JOIN sys.schemas AS sch ON sch.schema_id = tbl.schema_id
        INNER JOIN sys.stats AS st ON st.object_id = tbl.object_id
        OUTER APPLY sys.dm_db_stats_properties(st.object_id, st.stats_id) AS props
        WHERE tbl.is_ms_shipped = 0;
        SQL;

    // Los page splits suelen subir cuando un índice está mal ajustado para la carga.
    // Por eso se guardan para ver tendencia, no solo una foto aislada.
    private const PAGE_SPLITS_QUERY = <<<'SQL'
        SET NOCOUNT ON;

        SELECT
            ops.object_id,
            ops.index_id,
            SUM(ops.leaf_allocation_count) AS leaf_page_split_count,
            SUM(ops.nonleaf_allocation_count) AS nonleaf_page_split_count,
            SUM(ops.leaf_allocation_count) + SUM(ops.nonleaf_allocation_count) AS page_split_count
        FROM sys.dm_db_index_operational_stats(DB_ID(), NULL, NULL, NULL) AS ops
        INNER JOIN sys.indexes AS idx
            ON idx.object_id = ops.object_id AND idx.index_id = ops.index_id
        INNER JOIN sys.tables AS tbl ON tbl.object_id = ops.object_id
        WHERE tbl.is_ms_shipped = 0
          AND idx.type IN (1, 2)
          AND idx.is_hypothetical = 0
          AND idx.is_disabled = 0
        GROUP BY ops.object_id, ops.index_id
        OPTION (MAXDOP 1);
        SQL;

    // Missing indexes no crea nada por sí solo, pero aporta candidatos con impacto
    // estimado para que el sistema pueda priorizar recomendaciones.
    private const MISSING_INDEXES_QUERY = <<<'SQL'
        SET NOCOUNT ON;

        SELECT
            tbl.object_id,
            sch.name AS schema_name,
            tbl.name AS table_name,
            mig.index_group_handle,
            mid.equality_columns,
            mid.inequality_columns,
            mid.included_columns,
            CONVERT(decimal(19, 2), migs.avg_total_user_cost * migs.avg_user_impact * (migs.user_seeks + migs.user_scans)) AS estimated_impact,
            migs.user_seeks,
            migs.user_scans,
            migs.avg_total_user_cost,
            migs.avg_user_impact,
            migs.last_user_seek,
            migs.last_user_scan
        FROM sys.dm_db_missing_index_groups AS mig
        INNER JOIN sys.dm_db_missing_index_group_stats AS migs
            ON migs.group_handle = mig.index_group_handle
        INNER JOIN sys.dm_db_missing_index_details AS mid
            ON mid.index_handle = mig.index_handle
        INNER JOIN sys.tables AS tbl ON tbl.object_id = mid.object_id
        INNER JOIN sys.schemas AS sch ON sch.schema_id = tbl.schema_id
        WHERE tbl.is_ms_shipped = 0
          AND mid.database_id = DB_ID()
        ORDER BY estimated_impact DESC;
        SQL;

    public function __construct(private readonly SqlServerCapabilityService $capabilityService) {}

    public function inspect(Connection $connection, int $minimumIndexPages): InspectionResult
    {
        // Primero se detectan capacidades del servidor y luego se decide qué métricas
        // conviene ejecutar. Así evitamos consultas que el entorno no soporta.
        $capabilities = $this->capabilityService->inspect($connection);
        $warnings = [];

        if ($capabilities->hasViewDefinition !== true) {
            $warnings['inventory'] = 'VIEW DEFINITION is unavailable; inventory may be incomplete.';
        }

        $inventory = $connection->select(self::INVENTORY_QUERY);
        $fragmentation = $this->optionalQuery(
            $connection,
            'fragmentation',
            self::FRAGMENTATION_QUERY,
            [max(0, $minimumIndexPages)],
            $warnings,
        );
        $usage = $this->optionalQuery($connection, 'usage', self::USAGE_QUERY, [], $warnings);
        $statistics = $this->optionalQuery($connection, 'statistics', self::STATISTICS_QUERY, [], $warnings);
        $pageSplits = $this->optionalQuery($connection, 'page_splits', self::PAGE_SPLITS_QUERY, [], $warnings);
        $missingIndexes = $this->optionalQuery($connection, 'missing_indexes', self::MISSING_INDEXES_QUERY, [], $warnings);

        $fragmentationByKey = $this->keyRows($fragmentation);
        $usageByKey = $this->keyRows($usage);
        $serverStartedAt = isset($usage[0]) ? $this->date($usage[0]->sqlserver_start_time) : null;
        $indexes = [];

        foreach ($inventory as $row) {
            // Cada fila del inventario se convierte en un DTO rico con métricas cruzadas.
            // El objetivo es tener un objeto ya listo para persistir y evaluar alertas.
            $key = $this->key($row->object_id, $row->index_id);
            $physical = $fragmentationByKey[$key] ?? null;
            $counters = $usageByKey[$key] ?? null;

            $indexes[] = new IndexMetric(
                objectId: (int) $row->object_id,
                indexId: (int) $row->index_id,
                schemaName: (string) $row->schema_name,
                tableName: (string) $row->table_name,
                indexName: (string) $row->index_name,
                type: (string) $row->index_type,
                isUnique: (bool) $row->is_unique,
                isPrimaryKey: (bool) $row->is_primary_key,
                isDisabled: (bool) $row->is_disabled,
                fillFactor: (int) $row->fill_factor === 0 ? 100 : (int) $row->fill_factor,
                fragmentationPercent: $physical ? (float) $physical->fragmentation_percent : null,
                sizeMb: $physical ? (float) $physical->size_mb : null,
                pageCount: $physical ? (int) $physical->page_count : null,
                userSeeks: $counters ? (int) $counters->user_seeks : 0,
                userScans: $counters ? (int) $counters->user_scans : 0,
                userLookups: $counters ? (int) $counters->user_lookups : 0,
                userUpdates: $counters ? (int) $counters->user_updates : 0,
                lastUserSeekAt: $this->date($counters?->last_user_seek_at),
                lastUserScanAt: $this->date($counters?->last_user_scan_at),
                lastUserLookupAt: $this->date($counters?->last_user_lookup_at),
                usageStatsSince: $this->date($counters?->sqlserver_start_time),
            );
        }

        // El resultado final encapsula todo el estado recolectado durante la inspección.
        return new InspectionResult(
            capabilities: $capabilities,
            indexes: $indexes,
            statistics: array_map(fn (object $row) => new StatisticsMetric(
                objectId: (int) $row->object_id,
                statsId: (int) $row->stats_id,
                schemaName: (string) $row->schema_name,
                tableName: (string) $row->table_name,
                statsName: (string) $row->stats_name,
                rowCount: (int) $row->row_count,
                modificationCount: (int) $row->modification_count,
                modificationPercent: $row->modification_percent === null ? null : (float) $row->modification_percent,
                lastUpdatedAt: $this->date($row->last_updated_at),
            ), $statistics),
            pageSplits: array_map(fn (object $row) => new PageSplitMetric(
                objectId: (int) $row->object_id,
                indexId: (int) $row->index_id,
                leafCount: (int) $row->leaf_page_split_count,
                nonleafCount: (int) $row->nonleaf_page_split_count,
                totalCount: (int) $row->page_split_count,
            ), $pageSplits),
            missingIndexes: array_map(fn (object $row) => new MissingIndexMetric(
                schemaName: (string) $row->schema_name,
                tableName: (string) $row->table_name,
                objectId: (int) $row->object_id,
                indexGroupHandle: (int) $row->index_group_handle,
                equalityColumns: $this->parseColumns($row->equality_columns),
                inequalityColumns: $this->parseColumns($row->inequality_columns),
                includedColumns: $this->parseColumns($row->included_columns),
                estimatedImpact: (float) $row->estimated_impact,
                userSeeks: (int) $row->user_seeks,
                userScans: (int) $row->user_scans,
                avgTotalUserCost: (float) $row->avg_total_user_cost,
                avgUserImpact: (float) $row->avg_user_impact,
                lastUserSeekAt: $row->last_user_seek !== null ? (int) $row->last_user_seek : null,
                lastUserScanAt: $row->last_user_scan !== null ? (int) $row->last_user_scan : null,
            ), $missingIndexes),
            warnings: $warnings,
            serverStartedAt: $serverStartedAt,
        );
    }

    /** @return list<string> */
    private function parseColumns(?string $columns): array
    {
        if ($columns === null || $columns === '') {
            return [];
        }

        return array_map('trim', explode(',', $columns));
    }

    /** @return list<object> */
    private function optionalQuery(
        Connection $connection,
        string $metric,
        string $query,
        array $bindings,
        array &$warnings,
    ): array {
        try {
            // Algunas métricas pueden fallar por permisos o versión del motor.
            // En esos casos devolvemos vacío y registramos una advertencia.
            return $connection->select($query, $bindings);
        } catch (Throwable $exception) {
            $code = $exception->getCode();
            $warnings[$metric] = "Metric unavailable (driver code {$code}).";

            return [];
        }
    }

    /** @param list<object> $rows */
    private function keyRows(array $rows): array
    {
        $keyed = [];

        foreach ($rows as $row) {
            $keyed[$this->key($row->object_id, $row->index_id)] = $row;
        }

        return $keyed;
    }

    private function key(mixed $objectId, mixed $indexId): string
    {
        return $objectId.':'.$indexId;
    }

    private function date(mixed $value): ?DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Normaliza fechas del driver a un tipo estándar de PHP/Laravel.
        return $value instanceof DateTimeInterface ? $value : new DateTimeImmutable((string) $value);
    }
}
