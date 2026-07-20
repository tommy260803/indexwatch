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

class DuplicateIndexesAnalyzer
{
    public function analyze(Server $server, Collection $indexes): Collection
    {
        $findings = collect();
        $byTable = $indexes->groupBy(fn (SqlIndex $idx) => $idx->schema_name . '.' . $idx->table_name);

        foreach ($byTable as $tableKey => $tableIndexes) {
            if ($tableIndexes->count() < 2) {
                continue;
            }

            $comparisons = $this->compareIndexes($tableIndexes);

            foreach ($comparisons as $comparison) {
                $finding = $this->createFinding($server, $comparison);
                $findings->push($finding);
            }
        }

        return $findings;
    }

    private function compareIndexes(Collection $indexes): array
    {
        $comparisons = [];
        $items = $indexes->values()->all();

        for ($i = 0; $i < count($items); $i++) {
            for ($j = $i + 1; $j < count($items); $j++) {
                $idxA = $items[$i];
                $idxB = $items[$j];

                $relationship = $this->analyzeRelationship($idxA, $idxB);
                if ($relationship) {
                    $comparisons[] = array_merge($relationship, ['index_a' => $idxA, 'index_b' => $idxB]);
                }
            }
        }

        return $comparisons;
    }

    private function analyzeRelationship(SqlIndex $idxA, SqlIndex $idxB): ?array
    {
        $keyColsA = $this->getKeyColumns($idxA);
        $keyColsB = $this->getKeyColumns($idxB);
        $incColsA = $this->getIncludedColumns($idxA);
        $incColsB = $this->getIncludedColumns($idxB);

        $aCoversB = $this->covers($keyColsA, $incColsA, $keyColsB, $incColsB);
        $bCoversA = $this->covers($keyColsB, $incColsB, $keyColsA, $incColsA);

        if ($aCoversB && $bCoversA) {
            return [
                'type' => 'equivalent',
                'description' => 'Índices equivalentes (mismas claves e incluidas)',
                'severity' => FindingSeverity::Warning,
                'confidence' => ConfidenceLevel::High,
            ];
        }

        if ($aCoversB) {
            return [
                'type' => 'a_covers_b',
                'description' => "Índice {$idxA->index_name} cubre a {$idxB->index_name}",
                'severity' => FindingSeverity::Warning,
                'confidence' => ConfidenceLevel::High,
            ];
        }

        if ($bCoversA) {
            return [
                'type' => 'b_covers_a',
                'description' => "Índice {$idxB->index_name} cubre a {$idxA->index_name}",
                'severity' => FindingSeverity::Warning,
                'confidence' => ConfidenceLevel::High,
            ];
        }

        $prefixMatch = $this->commonPrefix($keyColsA, $keyColsB);
        if ($prefixMatch >= 2) {
            return [
                'type' => 'common_prefix',
                'description' => "Prefijo común de {$prefixMatch} columnas entre {$idxA->index_name} y {$idxB->index_name}",
                'severity' => FindingSeverity::Info,
                'confidence' => ConfidenceLevel::Medium,
            ];
        }

        return null;
    }

    private function getKeyColumns(SqlIndex $index): array
    {
        return [];
    }

    private function getIncludedColumns(SqlIndex $index): array
    {
        return [];
    }

    private function covers(array $keyA, array $incA, array $keyB, array $incB): bool
    {
        if (! $this->isPrefix($keyB, $keyA)) {
            return false;
        }

        $remainingB = array_slice($keyB, count($keyA));
        $allNeeded = array_merge($remainingB, $incB);

        return $this->allIn($allNeeded, array_merge($keyA, $incA));
    }

    private function isPrefix(array $needle, array $haystack): bool
    {
        if (count($needle) > count($haystack)) {
            return false;
        }

        for ($i = 0; $i < count($needle); $i++) {
            if (strcasecmp($needle[$i], $haystack[$i]) !== 0) {
                return false;
            }
        }

        return true;
    }

    private function allIn(array $needles, array $haystack): bool
    {
        $haystackLower = array_map('strtolower', $haystack);
        foreach ($needles as $needle) {
            if (! in_array(strtolower($needle), $haystackLower, true)) {
                return false;
            }
        }
        return true;
    }

    private function commonPrefix(array $a, array $b): int
    {
        $count = 0;
        $min = min(count($a), count($b));
        for ($i = 0; $i < $min; $i++) {
            if (strcasecmp($a[$i], $b[$i]) === 0) {
                $count++;
            } else {
                break;
            }
        }
        return $count;
    }

    private function createFinding(Server $server, array $comparison): Finding
    {
        $idxA = $comparison['index_a'];
        $idxB = $comparison['index_b'];

        $evidence = [
            Evidence::text('index_a', 'Índice A', $idxA->qualifiedName()),
            Evidence::text('index_b', 'Índice B', $idxB->qualifiedName()),
            Evidence::text('relationship_type', 'Tipo de relación', $comparison['type']),
            Evidence::metric('size_a_mb', 'Tamaño A (MB)', $idxA->size_mb ?? 0),
            Evidence::metric('size_b_mb', 'Tamaño B (MB)', $idxB->size_mb ?? 0),
            Evidence::metric('page_count_a', 'Páginas A', $idxA->page_count ?? 0),
            Evidence::metric('page_count_b', 'Páginas B', $idxB->page_count ?? 0),
            Evidence::text('is_pk_a', 'A es PK', $idxA->is_primary_key ? 'Sí' : 'No'),
            Evidence::text('is_pk_b', 'B es PK', $idxB->is_primary_key ? 'Sí' : 'No'),
            Evidence::text('is_unique_a', 'A es Unique', $idxA->is_unique ? 'Sí' : 'No'),
            Evidence::text('is_unique_b', 'B es Unique', $idxB->is_unique ? 'Sí' : 'No'),
        ];

        return new Finding(
            type: FindingType::DuplicateIndex,
            fingerprint: $this->makeFingerprint($idxA, $idxB, $comparison['type']),
            severity: $comparison['severity'],
            confidence: $comparison['confidence'],
            title: "Índices duplicados/redundantes: {$idxA->qualifiedName()} vs {$idxB->qualifiedName()}",
            description: $comparison['description'],
            evidence: $evidence,
            recommendedAction: 'REVIEW',
            metadata: [
                'index_a_id' => $idxA->id,
                'index_b_id' => $idxB->id,
                'index_a_name' => $idxA->index_name,
                'index_b_name' => $idxB->index_name,
                'index_a_schema' => $idxA->schema_name,
                'index_b_schema' => $idxB->schema_name,
                'index_a_table' => $idxA->table_name,
                'index_b_table' => $idxB->table_name,
                'relationship_type' => $comparison['type'],
                'index_a_is_pk' => $idxA->is_primary_key,
                'index_b_is_pk' => $idxB->is_primary_key,
                'index_a_is_unique' => $idxA->is_unique,
                'index_b_is_unique' => $idxB->is_unique,
                'finding_confidence' => $comparison['confidence']->value,
            ],
            serverId: $server->id,
            sqlIndexId: $idxA->id,
            subjectType: SqlIndex::class,
            subjectId: $idxA->id,
        );
    }

    private function makeFingerprint(SqlIndex $a, SqlIndex $b, string $type): string
    {
        $ids = [$a->id, $b->id];
        sort($ids);
        return hash('sha256', "dup:{$type}:{$ids[0]}:{$ids[1]}");
    }
}