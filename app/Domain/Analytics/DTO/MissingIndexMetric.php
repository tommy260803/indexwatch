<?php

namespace App\Domain\Analytics\DTO;

use App\Domain\Analytics\Enums\FindingType;

final readonly class MissingIndexMetric
{
    public function __construct(
        public string $schemaName,
        public string $tableName,
        public int $objectId,
        public int $indexGroupHandle,
        public array $equalityColumns,
        public array $inequalityColumns,
        public array $includedColumns,
        public float $estimatedImpact,
        public int $userSeeks,
        public int $userScans,
        public float $avgTotalUserCost,
        public float $avgUserImpact,
        public ?int $lastUserSeekAt = null,
        public ?int $lastUserScanAt = null,
    ) {}

    public function fingerprint(): string
    {
        $eq = $this->equalityColumns;
        $ineq = $this->inequalityColumns;
        $inc = $this->includedColumns;
        sort($eq);
        sort($ineq);
        sort($inc);

        return hash('sha256', "{$this->tableName}|" . implode(',', $eq) . '|' . implode(',', $ineq) . '|' . implode(',', $inc));
    }

    public function estimatedMonthlyImpact(): float
    {
        return $this->estimatedImpact * ($this->userSeeks + $this->userScans) / 1000.0;
    }
}