<?php

namespace App\Domain\Monitoring\DTO;

final readonly class ScanPersistenceResult
{
    public function __construct(
        public int $indexes,
        public int $snapshots,
        public int $statistics,
        public int $pageSplitSamples,
        public int $sustainedPageSplitIndexes,
        public int $alertsCreated,
        public HealthScoreResult $healthScore,
    ) {}

    public function toArray(): array
    {
        return [
            'indexes' => $this->indexes,
            'snapshots' => $this->snapshots,
            'statistics' => $this->statistics,
            'page_split_samples' => $this->pageSplitSamples,
            'sustained_page_split_indexes' => $this->sustainedPageSplitIndexes,
            'alerts_created' => $this->alertsCreated,
            'health_score' => $this->healthScore->score,
            'health_score_version' => $this->healthScore->version,
        ];
    }
}
