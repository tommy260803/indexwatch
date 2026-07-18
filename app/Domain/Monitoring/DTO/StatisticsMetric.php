<?php

namespace App\Domain\Monitoring\DTO;

use DateTimeInterface;

final readonly class StatisticsMetric
{
    public function __construct(
        public int $objectId,
        public int $statsId,
        public string $schemaName,
        public string $tableName,
        public string $statsName,
        public int $rowCount,
        public int $modificationCount,
        public ?float $modificationPercent,
        public ?DateTimeInterface $lastUpdatedAt,
    ) {}
}
