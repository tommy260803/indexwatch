<?php

namespace App\Services\Monitoring;

use App\Domain\Monitoring\DTO\InspectionResult;
use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\RecommendedAction;
use App\Models\Alert;
use App\Models\Server;
use App\Models\SqlIndex;
use App\Models\StatisticsStatus;

class AlertDetectionService
{
    /** @var list<string> */
    private array $seenFingerprints = [];

    /** @var array<string, true> */
    private array $freshPageSplitKeys = [];

    public function detect(Server $server, InspectionResult $result): int
    {
        $this->seenFingerprints = [];
        $this->freshPageSplitKeys = array_fill_keys(
            array_map(fn ($metric) => $metric->stableKey(), $result->pageSplits),
            true,
        );
        $count = 0;
        $availableTypes = [];

        $fragmentationAvailable = ! isset($result->warnings['fragmentation']);
        $usageAvailable = ! isset($result->warnings['usage']);
        $pageSplitsAvailable = ! isset($result->warnings['page_splits']);
        $statisticsAvailable = ! isset($result->warnings['statistics']);

        foreach ($server->sqlIndexes()->active()->get() as $index) {
            if ($fragmentationAvailable) {
                $count += $this->detectFragmentation($server, $index);
            }

            if ($usageAvailable) {
                $count += $this->detectFillFactor($server, $index);
            }

            if ($pageSplitsAvailable && isset($this->freshPageSplitKeys[$index->object_id.':'.$index->index_id_native])) {
                $count += $this->detectPageSplits($server, $index);
                $this->dismissRecoveredSubjectFinding($server, AlertType::PageSplits, $index);
            }
        }

        if ($statisticsAvailable) {
            foreach ($server->statisticsStatuses()
                ->where('modification_ratio', '>=', $server->stats_stale_threshold)
                ->get() as $statistics) {
                $count += $this->storeFinding(
                    server: $server,
                    type: AlertType::StaleStatistics,
                    severity: AlertSeverity::Warning,
                    action: RecommendedAction::UpdateStatistics,
                    subject: $statistics,
                    metadata: [
                        'schema_name' => $statistics->schema_name,
                        'table_name' => $statistics->table_name,
                        'stats_name' => $statistics->stats_name,
                        'row_count' => $statistics->row_count,
                        'modification_count' => $statistics->modification_count,
                        'modification_percent' => (float) $statistics->modification_ratio,
                        'threshold' => (float) $server->stats_stale_threshold,
                    ],
                );
            }
        }

        $completeInventory = $result->capabilities->hasViewDefinition === true;
        $availableTypes = array_values(array_filter([
            $fragmentationAvailable && $completeInventory ? AlertType::Fragmentation : null,
            $usageAvailable && $completeInventory ? AlertType::FillFactor : null,
            $statisticsAvailable
                && $completeInventory
                && $result->capabilities->hasDatabaseSelect === true
                    ? AlertType::StaleStatistics
                    : null,
        ]));
        $this->dismissRecoveredFindings($server, $availableTypes);

        return $count;
    }

    private function detectFragmentation(Server $server, SqlIndex $index): int
    {
        $fragmentation = $index->fragmentation_percent;

        if ($fragmentation === null || (float) $fragmentation < (float) $server->warning_threshold) {
            return 0;
        }

        $critical = (float) $fragmentation >= (float) $server->critical_threshold;

        return $this->storeFinding(
            server: $server,
            type: AlertType::Fragmentation,
            severity: $critical ? AlertSeverity::Critical : AlertSeverity::Warning,
            action: $critical ? RecommendedAction::Rebuild : RecommendedAction::Reorganize,
            subject: $index,
            metadata: [
                'schema_name' => $index->schema_name,
                'table_name' => $index->table_name,
                'index_name' => $index->index_name,
                'fragmentation_percent' => (float) $fragmentation,
                'page_count' => $index->page_count,
                'warning_threshold' => (float) $server->warning_threshold,
                'critical_threshold' => (float) $server->critical_threshold,
            ],
            fragmentation: (float) $fragmentation,
        );
    }

    private function detectFillFactor(Server $server, SqlIndex $index): int
    {
        if ($index->optimal_fill_factor === null
            || abs($index->optimal_fill_factor - $index->fill_factor) < 5) {
            return 0;
        }

        return $this->storeFinding(
            server: $server,
            type: AlertType::FillFactor,
            severity: AlertSeverity::Info,
            action: RecommendedAction::Review,
            subject: $index,
            metadata: [
                'current_fill_factor' => $index->fill_factor,
                'recommended_fill_factor' => $index->optimal_fill_factor,
                'reason' => $index->fill_factor_reason,
                'reads' => $index->getTotalReads(),
                'writes' => $index->getTotalWrites(),
                'usage_stats_since' => $index->usage_stats_since?->toAtomString(),
            ],
        );
    }

    private function detectPageSplits(Server $server, SqlIndex $index): int
    {
        $samples = $index->operationalSnapshots()->latest('sampled_at')->limit(2)->get();
        $threshold = (float) config('indexwatch.page_splits.warning_per_minute');

        if ($samples->count() < 2
            || $samples[0]->page_splits_per_minute === null
            || $samples[1]->page_splits_per_minute === null
            || (float) $samples[0]->page_splits_per_minute < $threshold
            || (float) $samples[1]->page_splits_per_minute < $threshold) {
            return 0;
        }

        $critical = (float) $samples[0]->page_splits_per_minute >= $threshold * 5;

        return $this->storeFinding(
            server: $server,
            type: AlertType::PageSplits,
            severity: $critical ? AlertSeverity::Critical : AlertSeverity::Warning,
            action: RecommendedAction::Review,
            subject: $index,
            metadata: [
                'current_delta' => $samples[0]->page_split_delta,
                'current_per_minute' => (float) $samples[0]->page_splits_per_minute,
                'previous_per_minute' => (float) $samples[1]->page_splits_per_minute,
                'threshold_per_minute' => $threshold,
                'sampled_at' => $samples[0]->sampled_at->toAtomString(),
            ],
        );
    }

    private function storeFinding(
        Server $server,
        AlertType $type,
        AlertSeverity $severity,
        RecommendedAction $action,
        SqlIndex|StatisticsStatus $subject,
        array $metadata,
        ?float $fragmentation = null,
    ): int {
        $sqlIndexId = $subject instanceof SqlIndex ? $subject->id : null;
        $fingerprint = Alert::makeFingerprint(
            $server->id,
            $type,
            $sqlIndexId,
            $subject::class,
            $subject->id,
        );
        $this->seenFingerprints[] = $fingerprint;
        $openStatuses = array_map(
            fn (AlertStatus $status) => $status->value,
            AlertStatus::openStatuses(),
        );
        $alert = Alert::query()
            ->where('server_id', $server->id)
            ->where('fingerprint', $fingerprint)
            ->whereIn('status', $openStatuses)
            ->first();

        if ($alert !== null && in_array($alert->status, [
            AlertStatus::Approved,
            AlertStatus::Scheduled,
            AlertStatus::Running,
        ], true)) {
            return 0;
        }

        $created = $alert === null;
        $alert ??= new Alert;
        $alert->forceFill([
            'server_id' => $server->id,
            'sql_index_id' => $sqlIndexId,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'fingerprint' => $fingerprint,
            'alert_type' => $type,
            'severity' => $severity,
            'status' => $alert->exists ? $alert->status : AlertStatus::Pending,
            'recommended_action' => $action,
            'fragmentation_percent' => $fragmentation,
            'metadata' => $metadata,
        ])->save();

        return $created ? 1 : 0;
    }

    /** @param list<AlertType> $availableTypes */
    private function dismissRecoveredFindings(Server $server, array $availableTypes): void
    {
        if ($availableTypes === []) {
            return;
        }

        Alert::query()
            ->where('server_id', $server->id)
            ->whereIn('alert_type', array_map(fn (AlertType $type) => $type->value, $availableTypes))
            ->whereIn('status', [
                AlertStatus::Pending->value,
                AlertStatus::Sent->value,
                AlertStatus::AwaitingResponse->value,
            ])
            ->when(
                $this->seenFingerprints !== [],
                fn ($query) => $query->whereNotIn('fingerprint', $this->seenFingerprints),
            )
            ->update([
                'status' => AlertStatus::Dismissed->value,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

        Alert::query()
            ->where('server_id', $server->id)
            ->whereIn('alert_type', array_map(fn (AlertType $type) => $type->value, $availableTypes))
            ->whereIn('status', [
                AlertStatus::Approved->value,
                AlertStatus::Scheduled->value,
            ])
            ->when(
                $this->seenFingerprints !== [],
                fn ($query) => $query->whereNotIn('fingerprint', $this->seenFingerprints),
            )
            ->update([
                'status' => AlertStatus::Expired->value,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function dismissRecoveredSubjectFinding(
        Server $server,
        AlertType $type,
        SqlIndex $subject,
    ): void {
        $fingerprint = Alert::makeFingerprint(
            $server->id,
            $type,
            $subject->id,
            $subject::class,
            $subject->id,
        );

        if (in_array($fingerprint, $this->seenFingerprints, true)) {
            return;
        }

        Alert::query()
            ->where('server_id', $server->id)
            ->where('fingerprint', $fingerprint)
            ->whereIn('status', [
                AlertStatus::Pending->value,
                AlertStatus::Sent->value,
                AlertStatus::AwaitingResponse->value,
            ])
            ->update([
                'status' => AlertStatus::Dismissed->value,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

        Alert::query()
            ->where('server_id', $server->id)
            ->where('fingerprint', $fingerprint)
            ->whereIn('status', [
                AlertStatus::Approved->value,
                AlertStatus::Scheduled->value,
            ])
            ->update([
                'status' => AlertStatus::Expired->value,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
