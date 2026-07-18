<?php

namespace App\Services\Monitoring;

use App\Domain\Monitoring\DTO\InspectionResult;
use App\Domain\Monitoring\DTO\ScanPersistenceResult;
use App\Enums\IndexRecordStatus;
use App\Models\IndexOperationalSnapshot;
use App\Models\IndexSnapshot;
use App\Models\Server;
use App\Models\SqlIndex;
use App\Models\StatisticsStatus;
use Illuminate\Support\Facades\DB;

class ScanPersistenceService
{
    public function __construct(
        private readonly FillFactorRecommendationService $fillFactor,
        private readonly HealthScoreService $healthScore,
        private readonly AlertDetectionService $alerts,
        private readonly PageSplitRateService $pageSplitRate,
    ) {}

    public function persist(
        Server $server,
        InspectionResult $result,
        \DateTimeInterface $scannedAt,
        int $scanRunId,
    ): ScanPersistenceResult {
        return DB::transaction(function () use ($server, $result, $scannedAt, $scanRunId): ScanPersistenceResult {
            $previousStartedAt = $server->sql_server_started_at;
            $sameCounterEpoch = $previousStartedAt !== null
                && $result->serverStartedAt !== null
                && $previousStartedAt->getTimestamp() === $result->serverStartedAt->getTimestamp();
            $seenIds = [];
            $snapshotCount = 0;
            $fragmentationAvailable = ! isset($result->warnings['fragmentation']);
            $usageAvailable = ! isset($result->warnings['usage']);

            foreach ($result->indexes as $metric) {
                $attributes = [
                    'schema_name' => $metric->schemaName,
                    'table_name' => $metric->tableName,
                    'index_name' => $metric->indexName,
                    'type' => $metric->type,
                    'is_unique' => $metric->isUnique,
                    'is_primary_key' => $metric->isPrimaryKey,
                    'is_disabled' => $metric->isDisabled,
                    'status' => IndexRecordStatus::Active,
                    'fill_factor' => $metric->fillFactor,
                    'last_checked_at' => $scannedAt,
                ];

                if ($fragmentationAvailable) {
                    $attributes = array_merge($attributes, [
                        'fragmentation_percent' => $metric->fragmentationPercent,
                        'size_mb' => $metric->sizeMb,
                        'page_count' => $metric->pageCount,
                    ]);
                }

                if ($usageAvailable) {
                    $recommendation = $this->fillFactor->recommend($metric);
                    $attributes = array_merge($attributes, [
                        'optimal_fill_factor' => $recommendation['value'],
                        'fill_factor_reason' => $recommendation['reason'],
                        'user_seeks' => $metric->userSeeks,
                        'user_scans' => $metric->userScans,
                        'user_lookups' => $metric->userLookups,
                        'user_updates' => $metric->userUpdates,
                        'last_user_seek_at' => $metric->lastUserSeekAt,
                        'last_user_scan_at' => $metric->lastUserScanAt,
                        'last_user_lookup_at' => $metric->lastUserLookupAt,
                        'usage_stats_since' => $metric->usageStatsSince,
                    ]);
                }

                $sqlIndex = SqlIndex::query()->updateOrCreate(
                    [
                        'server_id' => $server->id,
                        'object_id' => $metric->objectId,
                        'index_id_native' => $metric->indexId,
                    ],
                    $attributes,
                );
                $seenIds[] = $sqlIndex->id;

                if ($fragmentationAvailable && $metric->fragmentationPercent !== null) {
                    IndexSnapshot::query()->updateOrCreate([
                        'server_scan_run_id' => $scanRunId,
                        'sql_index_id' => $sqlIndex->id,
                    ], [
                        'fragmentation_percent' => $metric->fragmentationPercent,
                        'size_mb' => $metric->sizeMb,
                        'page_count' => $metric->pageCount,
                        'seeks' => $usageAvailable ? $metric->userSeeks : null,
                        'scans' => $usageAvailable ? $metric->userScans : null,
                        'lookups' => $usageAvailable ? $metric->userLookups : null,
                        'writes' => $usageAvailable ? $metric->userUpdates : null,
                        'fill_factor' => $metric->fillFactor,
                        'index_last_used_at' => $this->mostRecentUsage($metric),
                        'scanned_at' => $scannedAt,
                    ]);
                    $snapshotCount++;
                }
            }

            if ($result->capabilities->hasViewDefinition === true) {
                SqlIndex::query()
                    ->where('server_id', $server->id)
                    ->when($seenIds !== [], fn ($query) => $query->whereNotIn('id', $seenIds))
                    ->update(['status' => IndexRecordStatus::Dropped->value]);
            }

            $seenStatisticsIds = [];

            foreach ($result->statistics as $metric) {
                $statisticsStatus = StatisticsStatus::query()->updateOrCreate(
                    [
                        'server_id' => $server->id,
                        'object_id' => $metric->objectId,
                        'stats_id' => $metric->statsId,
                    ],
                    [
                        'schema_name' => $metric->schemaName,
                        'table_name' => $metric->tableName,
                        'stats_name' => $metric->statsName,
                        'row_count' => $metric->rowCount,
                        'modification_count' => $metric->modificationCount,
                        'modification_ratio' => $metric->modificationPercent,
                        'last_updated_at' => $metric->lastUpdatedAt,
                        'scanned_at' => $scannedAt,
                    ],
                );
                $seenStatisticsIds[] = $statisticsStatus->id;
            }

            if (! isset($result->warnings['statistics'])
                && $result->capabilities->hasViewDefinition === true
                && $result->capabilities->hasDatabaseSelect === true) {
                StatisticsStatus::query()
                    ->where('server_id', $server->id)
                    ->when($seenStatisticsIds !== [], fn ($query) => $query->whereNotIn('id', $seenStatisticsIds))
                    ->delete();
            }

            [$pageSplitSamples, $sustainedPageSplitIndexes] = $this->persistPageSplits(
                $server,
                $result,
                $scannedAt,
                $sameCounterEpoch,
                $scanRunId,
            );

            $previousDetails = $server->health_score_details ?? [];
            $unavailableComponents = array_values(array_intersect(
                array_keys($result->warnings),
                ['inventory', 'fragmentation', 'statistics', 'page_splits'],
            ));

            if ($result->capabilities->hasDatabaseSelect !== true
                && ! in_array('statistics', $unavailableComponents, true)) {
                $unavailableComponents[] = 'statistics';
            }

            $missingBaselines = array_filter($unavailableComponents, function (string $component) use ($previousDetails): bool {
                $detailKey = match ($component) {
                    'inventory', 'fragmentation' => 'critical_indexes.count',
                    'statistics' => 'stale_statistics.count',
                    'page_splits' => 'sustained_page_splits.count',
                };

                return data_get($previousDetails, $detailKey) === null;
            });
            $criticalIndexes = isset($result->warnings['inventory']) || isset($result->warnings['fragmentation'])
                ? (int) data_get($previousDetails, 'critical_indexes.count', 0)
                : SqlIndex::query()
                    ->where('server_id', $server->id)
                    ->where('status', IndexRecordStatus::Active)
                    ->where('fragmentation_percent', '>=', $server->critical_threshold)
                    ->count();
            $statisticsIncomplete = isset($result->warnings['statistics'])
                || $result->capabilities->hasViewDefinition !== true
                || $result->capabilities->hasDatabaseSelect !== true;
            $staleStatistics = $statisticsIncomplete
                ? (int) data_get($previousDetails, 'stale_statistics.count', 0)
                : StatisticsStatus::query()
                    ->where('server_id', $server->id)
                    ->where('modification_ratio', '>=', $server->stats_stale_threshold)
                    ->count();
            $sustainedPageSplitIndexes = isset($result->warnings['page_splits'])
                ? (int) data_get($previousDetails, 'sustained_page_splits.count', 0)
                : $sustainedPageSplitIndexes;
            $health = $missingBaselines === []
                ? $this->healthScore->calculate(
                    $criticalIndexes,
                    $staleStatistics,
                    $sustainedPageSplitIndexes,
                )
                : $this->healthScore->incomplete(array_values($missingBaselines));

            $server->forceFill([
                'sql_server_version' => $result->capabilities->productVersion,
                'sql_server_edition' => $result->capabilities->edition,
                'sql_server_capabilities' => $result->capabilities->toArray(),
                'sql_server_started_at' => $result->serverStartedAt,
                'health_score' => $health->score,
                'health_score_version' => $health->version,
                'health_score_details' => $health->details,
                'health_score_updated_at' => $scannedAt,
            ])->save();

            $alertsCreated = $this->alerts->detect($server->refresh(), $result);

            return new ScanPersistenceResult(
                indexes: count($result->indexes),
                snapshots: $snapshotCount,
                statistics: count($result->statistics),
                pageSplitSamples: $pageSplitSamples,
                sustainedPageSplitIndexes: $sustainedPageSplitIndexes,
                alertsCreated: $alertsCreated,
                healthScore: $health,
            );
        });
    }

    private function persistPageSplits(
        Server $server,
        InspectionResult $result,
        \DateTimeInterface $scannedAt,
        bool $sameCounterEpoch,
        int $scanRunId,
    ): array {
        $indexes = SqlIndex::query()
            ->where('server_id', $server->id)
            ->get()
            ->keyBy(fn (SqlIndex $index) => $index->object_id.':'.$index->index_id_native);
        $warningRate = (float) config('indexwatch.page_splits.warning_per_minute');
        $sustainedCount = 0;
        $sampleCount = 0;

        foreach ($result->pageSplits as $metric) {
            /** @var SqlIndex|null $sqlIndex */
            $sqlIndex = $indexes->get($metric->stableKey());

            if ($sqlIndex === null) {
                continue;
            }

            $previous = IndexOperationalSnapshot::query()
                ->where('sql_index_id', $sqlIndex->id)
                ->where(function ($query) use ($scanRunId) {
                    $query->whereNull('server_scan_run_id')
                        ->orWhere('server_scan_run_id', '!=', $scanRunId);
                })
                ->latest('sampled_at')
                ->first();
            $rateResult = $this->pageSplitRate->calculate(
                previousCount: $previous?->page_split_count,
                currentCount: $metric->totalCount,
                previousAt: $previous?->sampled_at,
                currentAt: $scannedAt,
                sameCounterEpoch: $sameCounterEpoch,
            );
            $delta = $rateResult['delta'];
            $elapsedSeconds = $rateResult['elapsed_seconds'];
            $rate = $rateResult['per_minute'];

            $wasHigh = $previous?->page_splits_per_minute !== null
                && (float) $previous->page_splits_per_minute >= $warningRate;

            IndexOperationalSnapshot::query()->updateOrCreate([
                'server_scan_run_id' => $scanRunId,
                'sql_index_id' => $sqlIndex->id,
            ], [
                'leaf_page_split_count' => $metric->leafCount,
                'nonleaf_page_split_count' => $metric->nonleafCount,
                'page_split_count' => $metric->totalCount,
                'page_split_delta' => $delta,
                'elapsed_seconds' => $elapsedSeconds,
                'page_splits_per_minute' => $rate,
                'sampled_at' => $scannedAt,
            ]);
            $sampleCount++;

            if ($wasHigh && $rate !== null && $rate >= $warningRate) {
                $sustainedCount++;
            }
        }

        return [$sampleCount, $sustainedCount];
    }

    private function mostRecentUsage($metric): ?\DateTimeInterface
    {
        $dates = array_filter([
            $metric->lastUserSeekAt,
            $metric->lastUserScanAt,
            $metric->lastUserLookupAt,
        ]);

        if ($dates === []) {
            return null;
        }

        usort($dates, fn ($left, $right) => $right <=> $left);

        return $dates[0];
    }
}
