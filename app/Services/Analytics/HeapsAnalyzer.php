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

class HeapsAnalyzer
{
    private const MIN_HEAP_SIZE_MB = 100;
    private const MIN_HEAP_ACTIVITY = 1000;

    public function analyze(Server $server, Collection $indexes): Collection
    {
        $findings = collect();

        $heaps = $indexes->filter(fn (SqlIndex $idx) => $idx->type === 'HEAP');

        foreach ($heaps as $heap) {
            if (! $this->shouldConsider($heap)) {
                continue;
            }

            $finding = $this->createFinding($server, $heap);
            $findings->push($finding);
        }

        return $findings;
    }

    private function shouldConsider(SqlIndex $heap): bool
    {
        $sizeMb = $heap->size_mb ?? 0;
        if ($sizeMb < self::MIN_HEAP_SIZE_MB) {
            return false;
        }

        $totalOps = $heap->user_seeks + $heap->user_scans + $heap->user_lookups + $heap->user_updates;
        if ($totalOps < self::MIN_HEAP_ACTIVITY) {
            return false;
        }

        return true;
    }

    private function createFinding(Server $server, SqlIndex $heap): Finding
    {
        $sizeMb = $heap->size_mb ?? 0;
        $totalReads = $heap->user_seeks + $heap->user_scans + $heap->user_lookups;
        $totalWrites = $heap->user_updates;

        $evidence = [
            Evidence::metric('size_mb', 'Tamaño (MB)', number_format($sizeMb, 2), 'MB'),
            Evidence::metric('user_seeks', 'Seeks', $heap->user_seeks),
            Evidence::metric('user_scans', 'Scans', $heap->user_scans),
            Evidence::metric('user_lookups', 'Lookups', $heap->user_lookups),
            Evidence::metric('user_updates', 'Updates', $heap->user_updates),
            Evidence::metric('total_reads', 'Total lecturas', $totalReads),
            Evidence::metric('total_writes', 'Total escrituras', $totalWrites),
            Evidence::text('schema_name', 'Esquema', $heap->schema_name),
            Evidence::text('table_name', 'Tabla', $heap->table_name),
            Evidence::text('index_name', 'Índice (HEAP)', $heap->index_name),
            Evidence::text('usage_stats_since', 'Contadores desde', $heap->usage_stats_since?->format('Y-m-d') ?: 'Desconocido'),
        ];

        $confidence = ConfidenceLevel::fromScore(70);

        return new Finding(
            type: FindingType::Heap,
            fingerprint: hash('sha256', "heap:{$server->id}:{$heap->object_id}"),
            severity: FindingSeverity::Warning,
            confidence: $confidence,
            title: "Tabla HEAP detectada: {$heap->schema_name}.{$heap->table_name}",
            description: "La tabla {$heap->schema_name}.{$heap->table_name} no tiene índice clúster (es HEAP). Tamaño: {$sizeMb} MB. Actividad: {$totalReads} lecturas, {$totalWrites} escrituras. Revisar si se beneficia de un índice clúster.",
            evidence: $evidence,
            recommendedAction: 'REVIEW',
            metadata: [
                'schema_name' => $heap->schema_name,
                'table_name' => $heap->table_name,
                'object_id' => $heap->object_id,
                'size_mb' => $sizeMb,
                'user_seeks' => $heap->user_seeks,
                'user_scans' => $heap->user_scans,
                'user_lookups' => $heap->user_lookups,
                'user_updates' => $heap->user_updates,
                'total_reads' => $totalReads,
                'total_writes' => $totalWrites,
                'usage_stats_since' => $heap->usage_stats_since?->toAtomString(),
                'finding_confidence' => $confidence->value,
            ],
            serverId: $server->id,
            sqlIndexId: $heap->id,
            subjectType: SqlIndex::class,
            subjectId: $heap->id,
        );
    }
}