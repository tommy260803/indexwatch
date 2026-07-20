<?php

namespace App\Services\Analytics;

use App\Domain\Analytics\DTO\Evidence;
use App\Domain\Analytics\DTO\Finding;
use App\Domain\Analytics\Enums\ConfidenceLevel;
use App\Domain\Analytics\Enums\FindingSeverity;
use App\Domain\Analytics\Enums\FindingType;
use App\Models\Server;
use App\Models\SqlIndex;
use Illuminate\Support\Collection;

class UnusedIndexesAnalyzer
{
    private const DEFAULT_MIN_OBSERVATION_DAYS = 30;
    private const MIN_WRITES_FOR_CONSIDERATION = 100;

    public function analyze(Server $server, Collection $indexes, ?int $minObservationDays = null): Collection
    {
        $minDays = $minObservationDays ?? self::DEFAULT_MIN_OBSERVATION_DAYS;
        $findings = collect();

        $candidates = $indexes->filter(function (SqlIndex $idx) use ($minDays) {
            return $this->isUnusedCandidate($idx, $minDays);
        });

        foreach ($candidates as $index) {
            if ($this->isProtected($index)) {
                continue;
            }

            $finding = $this->createFinding($server, $index, $minDays);
            $findings->push($finding);
        }

        return $findings;
    }

    private function isUnusedCandidate(SqlIndex $index, int $minObservationDays): bool
    {
        if ($index->is_primary_key || $index->is_unique) {
            return false;
        }

        if ($index->is_disabled) {
            return false;
        }

        $totalReads = $index->user_seeks + $index->user_scans + $index->user_lookups;
        if ($totalReads > 0) {
            return false;
        }

        if ($index->usageStatsAreRecent($minObservationDays)) {
            return false;
        }

        $totalWrites = $index->user_updates;
        if ($totalWrites < self::MIN_WRITES_FOR_CONSIDERATION) {
            return false;
        }

        return true;
    }

    private function isProtected(SqlIndex $index): bool
    {
        if ($index->is_primary_key || $index->is_unique) {
            return true;
        }

        $protectedNames = ['fk_', 'foreign_key'];
        foreach ($protectedNames as $prefix) {
            if (str_starts_with(strtolower($index->index_name), $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function createFinding(Server $server, SqlIndex $index, int $minDays): Finding
    {
        $totalReads = $index->user_seeks + $index->user_scans + $index->user_lookups;
        $totalWrites = $index->user_updates;
        $readWriteRatio = $totalWrites > 0 ? round($totalReads / $totalWrites, 2) : ($totalReads > 0 ? 100.0 : 0.0);

        $evidence = [
            Evidence::text('index_name', 'Índice', $index->qualifiedName()),
            Evidence::metric('user_seeks', 'Seeks', $index->user_seeks),
            Evidence::metric('user_scans', 'Scans', $index->user_scans),
            Evidence::metric('user_lookups', 'Lookups', $index->user_lookups),
            Evidence::metric('user_updates', 'Updates', $index->user_updates),
            Evidence::metric('total_reads', 'Total lecturas', $totalReads),
            Evidence::metric('total_writes', 'Total escrituras', $totalWrites),
            Evidence::metric('read_write_ratio', 'Ratio L/E', $readWriteRatio),
            Evidence::text('type', 'Tipo', $index->type?->value ?? 'Desconocido'),
            Evidence::text('unique', 'Único', $index->is_unique ? 'Sí' : 'No'),
            Evidence::text('primary_key', 'PK', $index->is_primary_key ? 'Sí' : 'No'),
            Evidence::text('disabled', 'Deshabilitado', $index->is_disabled ? 'Sí' : 'No'),
            Evidence::text('usage_stats_since', 'Contadores desde', $index->usage_stats_since?->format('Y-m-d') ?: 'Desconocido'),
            Evidence::metric('min_observation_days', 'Días mínimos observación', $minDays),
        ];

        $confidence = ConfidenceLevel::fromScore(75);

        return new Finding(
            type: FindingType::UnusedIndex,
            fingerprint: $index->fingerprint ?? hash('sha256', "unused:{$server->id}:{$index->id}"),
            severity: FindingSeverity::Info,
            confidence: $confidence,
            title: "Índice sin uso: {$index->qualifiedName()}",
            description: "El índice {$index->qualifiedName()} no registra lecturas (seeks/scans/lookups = 0) en {$minDays} días de observación, pero tiene {$totalWrites} escrituras. Ratio L/E: {$readWriteRatio}. Candidato a REVIEW para posible DISABLE/DROP.",
            evidence: $evidence,
            recommendedAction: 'REVIEW',
            metadata: [
                'schema_name' => $index->schema_name,
                'table_name' => $index->table_name,
                'index_name' => $index->index_name,
                'object_id' => $index->object_id,
                'index_id_native' => $index->index_id_native,
                'user_seeks' => $index->user_seeks,
                'user_scans' => $index->user_scans,
                'user_lookups' => $index->user_lookups,
                'user_updates' => $index->user_updates,
                'total_reads' => $totalReads,
                'total_writes' => $totalWrites,
                'read_write_ratio' => $readWriteRatio,
                'usage_stats_since' => $index->usage_stats_since?->toAtomString(),
                'min_observation_days' => $minDays,
                'finding_confidence' => $confidence->value,
            ],
            serverId: $server->id,
            sqlIndexId: $index->id,
            subjectType: SqlIndex::class,
            subjectId: $index->id,
        );
    }
}