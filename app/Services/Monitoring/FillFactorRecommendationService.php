<?php

namespace App\Services\Monitoring;

use App\Domain\Monitoring\DTO\IndexMetric;
use DateTimeImmutable;

class FillFactorRecommendationService
{
    /** @return array{value: ?int, reason: string} */
    public function recommend(IndexMetric $metric): array
    {
        $observedSince = $metric->usageStatsSince;
        $totalOperations = $metric->totalReads() + $metric->userUpdates;

        if ($observedSince === null || $observedSince > new DateTimeImmutable('-7 days') || $totalOperations < 100) {
            return [
                'value' => null,
                'reason' => 'Insufficient stable usage evidence; fill factor unchanged.',
            ];
        }

        if ($metric->userUpdates > $metric->totalReads()) {
            return [
                'value' => 80,
                'reason' => "Write-dominant workload: {$metric->userUpdates} writes vs {$metric->totalReads()} reads.",
            ];
        }

        if ($metric->totalReads() >= max(1, $metric->userUpdates) * 4) {
            return [
                'value' => 95,
                'reason' => "Read-dominant workload: {$metric->totalReads()} reads vs {$metric->userUpdates} writes.",
            ];
        }

        return [
            'value' => null,
            'reason' => 'Mixed workload; no automatic fill factor recommendation.',
        ];
    }
}
