<?php

namespace App\Domain\Monitoring\DTO;

final readonly class PageSplitMetric
{
    public function __construct(
        public int $objectId,
        public int $indexId,
        public int $leafCount,
        public int $nonleafCount,
        public int $totalCount,
    ) {}

    public function stableKey(): string
    {
        return $this->objectId.':'.$this->indexId;
    }
}
