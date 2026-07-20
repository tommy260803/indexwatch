<?php

namespace App\Services\Analytics;

use App\Domain\Analytics\DTO\Evidence;
use App\Domain\Analytics\DTO\Finding;
use App\Domain\Analytics\DTO\MissingIndexMetric;
use App\Domain\Analytics\Enums\ConfidenceLevel;
use App\Domain\Analytics\Enums\FindingSeverity;
use App\Domain\Analytics\Enums\FindingType;
use App\Models\MissingIndex;
use App\Models\Server;
use App\Models\SqlIndex;
use Illuminate\Support\Collection;

class MissingIndexesAnalyzer
{
    private const EXCLUDED_TABLE_PREFIXES = ['sys', 'MSreplication', 'queue_messages', 'filestream_tombstone'];
    private const MIN_IMPACT_THRESHOLD = 100.0;
    private const MIN_USER_SEEKS_SCANS = 10;

    public function analyze(Server $server, Collection $missingIndexMetrics): Collection
    {
        $findings = collect();
        $existingIndexes = $this->loadExistingIndexes($server);

        foreach ($missingIndexMetrics as $metric) {
            if (! $this->shouldConsider($metric)) {
                continue;
            }

            if ($this->isCoveredByExistingIndex($metric, $existingIndexes)) {
                continue;
            }

            $finding = $this->createFinding($server, $metric);
            $findings->push($finding);

            $this->persistMissingIndex($server, $metric, $finding);
        }

        return $findings;
    }

    private function shouldConsider(MissingIndexMetric $metric): bool
    {
        if ($metric->estimatedImpact < self::MIN_IMPACT_THRESHOLD) {
            return false;
        }

        if (($metric->userSeeks + $metric->userScans) < self::MIN_USER_SEEKS_SCANS) {
            return false;
        }

        foreach (self::EXCLUDED_TABLE_PREFIXES as $prefix) {
            if (str_starts_with(strtolower($metric->tableName), strtolower($prefix))) {
                return false;
            }
        }

        return true;
    }

    private function loadExistingIndexes(Server $server): Collection
    {
        return SqlIndex::query()
            ->where('server_id', $server->id)
            ->where('status', 'active')
            ->get()
            ->mapWithKeys(fn (SqlIndex $idx) => [$idx->object_id . ':' . $idx->index_id_native => $idx]);
    }

    private function isCoveredByExistingIndex(MissingIndexMetric $metric, Collection $existingIndexes): bool
    {
        foreach ($existingIndexes as $existing) {
            if ($this->indexCovers($metric, $existing)) {
                return true;
            }
        }
        return false;
    }

    private function indexCovers(MissingIndexMetric $metric, SqlIndex $existing): bool
    {
        $eqMatch = $this->columnsMatch($metric->equalityColumns, $this->getIndexKeyColumns($existing, true));
        $ineqMatch = $this->columnsMatch($metric->inequalityColumns, $this->getIndexKeyColumns($existing, false));
        $incMatch = $this->columnsMatch($metric->includedColumns, $this->getIndexIncludedColumns($existing));

        return $eqMatch && $ineqMatch && $incMatch;
    }

    private function getIndexKeyColumns(SqlIndex $index, bool $equalityOnly): array
    {
        return [];
    }

    private function getIndexIncludedColumns(SqlIndex $index): array
    {
        return [];
    }

    private function columnsMatch(array $required, array $available): bool
    {
        if (empty($required)) {
            return true;
        }

        $availableLower = array_map('strtolower', $available);
        foreach ($required as $col) {
            if (! in_array(strtolower($col), $availableLower, true)) {
                return false;
            }
        }
        return true;
    }

    private function createFinding(Server $server, MissingIndexMetric $metric): Finding
    {
        $confidence = $this->calculateConfidence($metric);

        $evidence = [
            Evidence::metric('estimated_impact', 'Impacto estimado (DMV)', number_format($metric->estimatedImpact, 2)),
            Evidence::metric('user_seeks', 'Búsquedas (seeks)', $metric->userSeeks),
            Evidence::metric('user_scans', 'Escaneos (scans)', $metric->userScans),
            Evidence::metric('avg_total_user_cost', 'Costo promedio usuario', number_format($metric->avgTotalUserCost, 2)),
            Evidence::metric('avg_user_impact', 'Impacto promedio usuario', number_format($metric->avgUserImpact, 2)),
            Evidence::text('equality_columns', 'Columnas de igualdad', implode(', ', $metric->equalityColumns) ?: '—'),
            Evidence::text('inequality_columns', 'Columnas de desigualdad', implode(', ', $metric->inequalityColumns) ?: '—'),
            Evidence::text('included_columns', 'Columnas incluidas', implode(', ', $metric->includedColumns) ?: '—'),
        ];

        $severity = match (true) {
            $metric->estimatedImpact > 10000 => FindingSeverity::Critical,
            $metric->estimatedImpact > 1000 => FindingSeverity::Warning,
            default => FindingSeverity::Info,
        };

        $tableSchema = $metric->schemaName . '.' . $metric->tableName;

        return new Finding(
            type: FindingType::MissingIndex,
            fingerprint: $metric->fingerprint(),
            severity: $severity,
            confidence: $confidence,
            title: "Índice faltante sugerido en {$tableSchema}",
            description: "DMV reporta índice faltante con impacto estimado {$metric->estimatedImpact}. Columnas clave: " .
                implode(', ', array_merge($metric->equalityColumns, $metric->inequalityColumns)) .
                ($metric->includedColumns ? " | Incluidas: " . implode(', ', $metric->includedColumns) : ''),
            evidence: $evidence,
            recommendedAction: 'REVIEW',
            metadata: [
                'schema_name' => $metric->schemaName,
                'table_name' => $metric->tableName,
                'object_id' => $metric->objectId,
                'index_group_handle' => $metric->indexGroupHandle,
                'equality_columns' => $metric->equalityColumns,
                'inequality_columns' => $metric->inequalityColumns,
                'included_columns' => $metric->includedColumns,
                'estimated_impact' => $metric->estimatedImpact,
                'user_seeks' => $metric->userSeeks,
                'user_scans' => $metric->userScans,
                'avg_total_user_cost' => $metric->avgTotalUserCost,
                'avg_user_impact' => $metric->avgUserImpact,
                'finding_confidence' => $confidence->value,
            ],
            serverId: $server->id,
            subjectType: MissingIndex::class,
        );
    }

    private function calculateConfidence(MissingIndexMetric $metric): ConfidenceLevel
    {
        $score = 50;

        if ($metric->estimatedImpact > 10000) $score += 25;
        elseif ($metric->estimatedImpact > 1000) $score += 15;
        elseif ($metric->estimatedImpact > 100) $score += 10;

        $totalOps = $metric->userSeeks + $metric->userScans;
        if ($totalOps > 10000) $score += 15;
        elseif ($totalOps > 1000) $score += 10;
        elseif ($totalOps > 100) $score += 5;

        if ($metric->avgUserImpact > 90) $score += 10;
        elseif ($metric->avgUserImpact > 50) $score += 5;

        return ConfidenceLevel::fromScore(min(100, $score));
    }

    private function persistMissingIndex(Server $server, MissingIndexMetric $metric, Finding $finding): void
    {
        MissingIndex::updateOrCreate(
            [
                'server_id' => $server->id,
                'fingerprint' => $metric->fingerprint(),
            ],
            [
                'schema_name' => $metric->schemaName,
                'table_name' => $metric->tableName,
                'object_id' => $metric->objectId,
                'index_group_handle' => $metric->indexGroupHandle,
                'equality_columns' => $metric->equalityColumns,
                'inequality_columns' => $metric->inequalityColumns,
                'included_columns' => $metric->includedColumns,
                'estimated_impact' => $metric->estimatedImpact,
                'user_seeks' => $metric->userSeeks,
                'user_scans' => $metric->userScans,
                'status' => 'candidate',
                'last_seen_at' => now(),
            ]
        );
    }
}