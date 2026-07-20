<?php

namespace App\Domain\Monitoring\DTO;

use DateTimeInterface;

final readonly class InspectionResult
{
    /**
     * @param  list<IndexMetric>  $indexes
     * @param  list<StatisticsMetric>  $statistics
     * @param  list<PageSplitMetric>  $pageSplits
     * @param  list<MissingIndexMetric>  $missingIndexes
     * @param  array<string, string>  $warnings
     */
    public function __construct(
        public SqlServerCapabilities $capabilities,
        public array $indexes,
        public array $statistics,
        public array $pageSplits,
        public array $missingIndexes,
        public array $warnings,
        public ?DateTimeInterface $serverStartedAt,
    ) {}
}
