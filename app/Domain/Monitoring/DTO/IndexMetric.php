<?php

namespace App\Domain\Monitoring\DTO;

use DateTimeInterface;

final readonly class IndexMetric
{
    public function __construct(
        public int $objectId,
        public int $indexId,
        public string $schemaName,
        public string $tableName,
        public string $indexName,
        public string $type,
        public bool $isUnique,
        public bool $isPrimaryKey,
        public bool $isDisabled,
        public int $fillFactor,
        public ?float $fragmentationPercent,
        public ?float $sizeMb,
        public ?int $pageCount,
        public int $userSeeks,
        public int $userScans,
        public int $userLookups,
        public int $userUpdates,
        public ?DateTimeInterface $lastUserSeekAt,
        public ?DateTimeInterface $lastUserScanAt,
        public ?DateTimeInterface $lastUserLookupAt,
        public ?DateTimeInterface $usageStatsSince,
    ) {}

    public function stableKey(): string
    {
        return $this->objectId.':'.$this->indexId;
    }

    public function totalReads(): int
    {
        return $this->userSeeks + $this->userScans + $this->userLookups;
    }
}
