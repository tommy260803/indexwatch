<?php

namespace App\Services\Maintenance;

use App\Enums\RecommendedAction;
use App\Models\Alert;
use App\Models\SqlIndex;
use App\Models\StatisticsStatus;
use InvalidArgumentException;

class TsqlGeneratorService
{
    public function generate(Alert $alert): string
    {
        $action = $alert->recommended_action?->value ?? $alert->responded_action?->value;

        if (! $action) {
            throw new InvalidArgumentException('Alert has no recommended or responded action');
        }

        return match ($action) {
            RecommendedAction::Rebuild->value       => $this->generateRebuild($alert),
            RecommendedAction::Reorganize->value    => $this->generateReorganize($alert),
            RecommendedAction::UpdateStatistics->value => $this->generateUpdateStatistics($alert),
            RecommendedAction::CreateIndex->value   => $this->generateCreateIndex($alert),
            RecommendedAction::DisableIndex->value  => $this->generateDisableIndex($alert),
            RecommendedAction::DropIndex->value     => $this->generateDropIndex($alert),
            RecommendedAction::CreateClustered->value => $this->generateCreateClustered($alert),
            default => '-- Acción no reconocida: ' . $action,
        };
    }

    private function generateRebuild(Alert $alert): string
    {
        $index = $this->getIndex($alert);
        $fillFactor = $index->optimal_fill_factor ?? 90;
        $online = $this->supportsOnline($alert->server) ? 'ONLINE = ON, ' : '';

        return sprintf(
            "ALTER INDEX [%s] ON [%s].[%s] REBUILD WITH (%sFILLFACTOR = %d, MAXDOP = 1);",
            $index->index_name,
            $index->schema_name,
            $index->table_name,
            $online,
            $fillFactor
        );
    }

    private function generateReorganize(Alert $alert): string
    {
        $index = $this->getIndex($alert);

        return sprintf(
            "ALTER INDEX [%s] ON [%s].[%s] REORGANIZE WITH (LOB_COMPACTION = ON);",
            $index->index_name,
            $index->schema_name,
            $index->table_name
        );
    }

    private function generateUpdateStatistics(Alert $alert): string
    {
        if ($alert->subject_type === StatisticsStatus::class && $alert->subject) {
            /** @var StatisticsStatus $stats */
            $stats = $alert->subject;
            return sprintf(
                "UPDATE STATISTICS [%s].[%s] [%s] WITH FULLSCAN;",
                $stats->schema_name,
                $stats->table_name,
                $stats->stats_name
            );
        }

        $index = $this->getIndex($alert);
        return sprintf(
            "UPDATE STATISTICS [%s].[%s] [%s] WITH FULLSCAN;",
            $index->schema_name,
            $index->table_name,
            $index->index_name
        );
    }

    private function generateCreateIndex(Alert $alert): string
    {
        $metadata = $alert->metadata ?? [];

        $schema    = $metadata['schema_name'] ?? 'dbo';
        $table     = $metadata['table_name'] ?? '';
        $equality  = $metadata['equality_columns'] ?? [];
        $inequality = $metadata['inequality_columns'] ?? [];
        $included  = $metadata['included_columns'] ?? [];

        $columns = array_merge($equality, $inequality);
        if (empty($columns)) {
            return '-- CREATE INDEX: no columns specified in metadata';
        }

        $indexName = $this->generateIndexName($table, $columns);

        $keyColumns = implode(', ', array_map(fn ($c) => "[$c] ASC", $columns));
        $includeClause = ! empty($included)
            ? ' INCLUDE (' . implode(', ', array_map(fn ($c) => "[$c]", $included)) . ')'
            : '';

        return sprintf(
            "CREATE NONCLUSTERED INDEX [%s] ON [%s].[%s] (%s)%s;",
            $indexName,
            $schema,
            $table,
            $keyColumns,
            $includeClause
        );
    }

    private function generateDisableIndex(Alert $alert): string
    {
        $index = $this->getIndex($alert);
        return sprintf(
            "ALTER INDEX [%s] ON [%s].[%s] DISABLE;",
            $index->index_name,
            $index->schema_name,
            $index->table_name
        );
    }

    private function generateDropIndex(Alert $alert): string
    {
        $index = $this->getIndex($alert);
        return sprintf(
            "DROP INDEX [%s] ON [%s].[%s];",
            $index->index_name,
            $index->schema_name,
            $index->table_name
        );
    }

    private function generateCreateClustered(Alert $alert): string
    {
        if ($alert->subject_type !== SqlIndex::class) {
            return '-- CREATE CLUSTERED: requires SqlIndex subject';
        }

        $index = $alert->sqlIndex;
        if (! $index) {
            return '-- CREATE CLUSTERED: index not found';
        }

        return sprintf(
            "CREATE CLUSTERED INDEX [%s] ON [%s].[%s] (%s);",
            $index->index_name,
            $index->schema_name,
            $index->table_name,
            implode(', ', $this->getClusteredKeyColumns($index))
        );
    }

    private function getIndex(Alert $alert): SqlIndex
    {
        $index = $alert->sqlIndex;
        if (! $index) {
            throw new InvalidArgumentException('Alert requires associated SqlIndex for this action');
        }
        return $index;
    }

    private function supportsOnline(\App\Models\Server $server): bool
    {
        $caps = $server->sql_server_capabilities ?? [];
        return ($caps['supports_online_index_operations'] ?? false) === true;
    }

    private function generateIndexName(string $table, array $columns): string
    {
        $prefix = 'IX_' . $table . '_';
        $suffix = implode('_', $columns);
        $name = $prefix . $suffix;

        // Truncate if too long (SQL Server max 128 chars)
        if (strlen($name) > 128) {
            $name = substr($name, 0, 128);
        }

        return $name;
    }

    private function getClusteredKeyColumns(SqlIndex $index): array
    {
        // For heaps becoming clustered, we'd need to analyze the table
        // For now, return the existing index columns if it's a non-clustered index being promoted
        return ['id']; // Placeholder - would need actual table analysis
    }
}