<?php

namespace Tests\Unit;

use App\Domain\Monitoring\DTO\IndexMetric;
use App\Services\Monitoring\FillFactorRecommendationService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class FillFactorRecommendationServiceTest extends TestCase
{
    public function test_recommends_lower_fill_factor_for_write_dominant_workload(): void
    {
        $recommendation = (new FillFactorRecommendationService)->recommend(
            $this->metric(reads: 100, writes: 1000),
        );

        $this->assertSame(80, $recommendation['value']);
        $this->assertStringContainsString('Write-dominant', $recommendation['reason']);
    }

    public function test_recommends_high_fill_factor_for_read_dominant_workload(): void
    {
        $recommendation = (new FillFactorRecommendationService)->recommend(
            $this->metric(reads: 1000, writes: 100),
        );

        $this->assertSame(95, $recommendation['value']);
    }

    public function test_does_not_recommend_when_observation_is_too_recent(): void
    {
        $recommendation = (new FillFactorRecommendationService)->recommend(
            $this->metric(reads: 1000, writes: 100, observedSince: new DateTimeImmutable('-1 day')),
        );

        $this->assertNull($recommendation['value']);
        $this->assertStringContainsString('Insufficient', $recommendation['reason']);
    }

    private function metric(
        int $reads,
        int $writes,
        ?DateTimeImmutable $observedSince = null,
    ): IndexMetric {
        return new IndexMetric(
            objectId: 1,
            indexId: 1,
            schemaName: 'dbo',
            tableName: 'orders',
            indexName: 'IX_orders_created_at',
            type: 'NONCLUSTERED',
            isUnique: false,
            isPrimaryKey: false,
            isDisabled: false,
            fillFactor: 100,
            fragmentationPercent: 10,
            sizeMb: 10,
            pageCount: 1280,
            userSeeks: $reads,
            userScans: 0,
            userLookups: 0,
            userUpdates: $writes,
            lastUserSeekAt: null,
            lastUserScanAt: null,
            lastUserLookupAt: null,
            usageStatsSince: $observedSince ?? new DateTimeImmutable('-30 days'),
        );
    }
}
