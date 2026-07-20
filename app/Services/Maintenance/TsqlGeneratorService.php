<?php

namespace App\Services\Maintenance;

use App\Enums\RecommendedAction;
use App\Models\Alert;
use App\Models\Server;
use App\Models\SqlIndex;
use App\Models\StatisticsStatus;
use InvalidArgumentException;

class TsqlGeneratorService
{
    private const ALLOWED_REBUILD_OPTIONS = [
        'ONLINE' => ['ON', 'OFF'],
        'MAXDOP' => null, // integer, validated separately
        'FILLFACTOR' => null, // integer 1–100
        'SORT_IN_TEMPDB' => ['ON', 'OFF'],
        'STATISTICS_NORECOMPUTE' => ['ON', 'OFF'],
        'DATA_COMPRESSION' => ['NONE', 'ROW', 'PAGE'],
    ];

    private const ALLOWED_REORGANIZE_OPTIONS = [
        'LOB_COMPACTION' => ['ON', 'OFF'],
    ];

    private const MAX_IDENTIFIER_LENGTH = 128;

    public function generate(Alert $alert): string
    {
        $action = $alert->recommended_action?->value ?? $alert->responded_action?->value;

        if (! $action) {
            throw new InvalidArgumentException('Alert has no recommended or responded action');
        }

        return match ($action) {
            RecommendedAction::Rebuild->value => $this->generateRebuild($alert),
            RecommendedAction::Reorganize->value => $this->generateReorganize($alert),
            RecommendedAction::UpdateStatistics->value => $this->generateUpdateStatistics($alert),
            RecommendedAction::CreateIndex->value => $this->generateCreateIndex($alert),
            RecommendedAction::DisableIndex->value => $this->generateDisableIndex($alert),
            RecommendedAction::DropIndex->value => $this->generateDropIndex($alert),
            RecommendedAction::CreateClustered->value => $this->generateCreateClustered($alert),
            default => throw new InvalidArgumentException("Unsupported action: {$action}"),
        };
    }

    private function generateRebuild(Alert $alert): string
    {
        $index = $this->getIndex($alert);
        $fillFactor = $this->clampFillFactor($index->fill_factor ?? 90);
        $onlineOption = $this->supportsOnline($alert->server) ? 'ONLINE = ON, ' : '';

        return sprintf(
            'ALTER INDEX %s ON %s.%s REBUILD WITH (%sFILLFACTOR = %d, MAXDOP = 1);',
            $this->escapeIdentifier($index->index_name),
            $this->escapeIdentifier($index->schema_name),
            $this->escapeIdentifier($index->table_name),
            $onlineOption,
            $fillFactor,
        );
    }

    private function generateReorganize(Alert $alert): string
    {
        $index = $this->getIndex($alert);

        return sprintf(
            'ALTER INDEX %s ON %s.%s REORGANIZE WITH (LOB_COMPACTION = ON);',
            $this->escapeIdentifier($index->index_name),
            $this->escapeIdentifier($index->schema_name),
            $this->escapeIdentifier($index->table_name),
        );
    }

    private function generateUpdateStatistics(Alert $alert): string
    {
        if ($alert->subject_type === StatisticsStatus::class && $alert->subject) {
            /** @var StatisticsStatus $stats */
            $stats = $alert->subject;

            return sprintf(
                'UPDATE STATISTICS %s.%s %s WITH FULLSCAN;',
                $this->escapeIdentifier($stats->schema_name),
                $this->escapeIdentifier($stats->table_name),
                $this->escapeIdentifier($stats->stats_name),
            );
        }

        $index = $this->getIndex($alert);

        return sprintf(
            'UPDATE STATISTICS %s.%s %s WITH FULLSCAN;',
            $this->escapeIdentifier($index->schema_name),
            $this->escapeIdentifier($index->table_name),
            $this->escapeIdentifier($index->index_name),
        );
    }

    private function generateCreateIndex(Alert $alert): string
    {
        $metadata = $alert->metadata ?? [];

        $schema = $metadata['schema_name'] ?? 'dbo';
        $table = $metadata['table_name'] ?? '';
        $equality = $metadata['equality_columns'] ?? [];
        $inequality = $metadata['inequality_columns'] ?? [];
        $included = $metadata['included_columns'] ?? [];

        $columns = array_merge($equality, $inequality);
        if (empty($columns) || $table === '') {
            return '-- CREATE INDEX: insufficient metadata (table or columns missing)';
        }

        $indexName = $this->generateIndexName($table, $columns);

        $keyColumns = implode(', ', array_map(
            fn ($c) => $this->escapeIdentifier($c).' ASC',
            $columns,
        ));

        $includeClause = ! empty($included)
            ? ' INCLUDE ('.implode(', ', array_map(
                fn ($c) => $this->escapeIdentifier($c),
                $included,
            )).')'
            : '';

        return sprintf(
            'CREATE NONCLUSTERED INDEX %s ON %s.%s (%s)%s;',
            $this->escapeIdentifier($indexName),
            $this->escapeIdentifier($schema),
            $this->escapeIdentifier($table),
            $keyColumns,
            $includeClause,
        );
    }

    private function generateDisableIndex(Alert $alert): string
    {
        $index = $this->getIndex($alert);

        return sprintf(
            'ALTER INDEX %s ON %s.%s DISABLE;',
            $this->escapeIdentifier($index->index_name),
            $this->escapeIdentifier($index->schema_name),
            $this->escapeIdentifier($index->table_name),
        );
    }

    private function generateDropIndex(Alert $alert): string
    {
        $index = $this->getIndex($alert);

        return sprintf(
            'DROP INDEX %s ON %s.%s;',
            $this->escapeIdentifier($index->index_name),
            $this->escapeIdentifier($index->schema_name),
            $this->escapeIdentifier($index->table_name),
        );
    }

    private function generateCreateClustered(Alert $alert): string
    {
        if ($alert->subject_type !== SqlIndex::class) {
            throw new InvalidArgumentException('CREATE CLUSTERED requires a SqlIndex subject');
        }

        $index = $alert->sqlIndex;
        if (! $index) {
            throw new InvalidArgumentException('CREATE CLUSTERED requires an associated SqlIndex');
        }

        return sprintf(
            'CREATE CLUSTERED INDEX %s ON %s.%s (%s);',
            $this->escapeIdentifier($index->index_name),
            $this->escapeIdentifier($index->schema_name),
            $this->escapeIdentifier($index->table_name),
            $this->escapeIdentifier($index->index_name),
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

    private function supportsOnline(Server $server): bool
    {
        $caps = $server->sql_server_capabilities ?? [];

        return ($caps['supports_online_index_operations'] ?? false) === true;
    }

    /**
     * Centralized SQL Server identifier escaping. Prevents injection by always
     * wrapping identifiers in square brackets and rejecting empty/malicious input.
     */
    public function escapeIdentifier(string $identifier): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException('SQL identifier cannot be empty');
        }

        // Strip any existing brackets to prevent double-escaping
        $clean = str_replace(['[', ']'], '', $identifier);

        if ($clean === '') {
            throw new InvalidArgumentException('SQL identifier contains only brackets');
        }

        if (mb_strlen($clean) > self::MAX_IDENTIFIER_LENGTH) {
            throw new InvalidArgumentException(
                'SQL identifier exceeds '.self::MAX_IDENTIFIER_LENGTH." characters: {$clean}"
            );
        }

        return "[{$clean}]";
    }

    private function generateIndexName(string $table, array $columns): string
    {
        $prefix = 'IX_'.$table.'_';
        $suffix = implode('_', $columns);
        $name = $prefix.$suffix;

        if (mb_strlen($name) > self::MAX_IDENTIFIER_LENGTH) {
            $name = mb_substr($name, 0, self::MAX_IDENTIFIER_LENGTH);
        }

        return $name;
    }

    private function clampFillFactor(mixed $fillFactor): int
    {
        $value = (int) $fillFactor;

        return max(1, min(100, $value ?: 90));
    }
}
