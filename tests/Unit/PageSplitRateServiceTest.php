<?php

namespace Tests\Unit;

use App\Services\Monitoring\PageSplitRateService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PageSplitRateServiceTest extends TestCase
{
    public function test_it_calculates_delta_and_normalized_rate(): void
    {
        $result = (new PageSplitRateService)->calculate(
            previousCount: 1000,
            currentCount: 1300,
            previousAt: new DateTimeImmutable('2026-07-18 10:00:00'),
            currentAt: new DateTimeImmutable('2026-07-18 10:05:00'),
            sameCounterEpoch: true,
        );

        $this->assertSame(300, $result['delta']);
        $this->assertSame(300, $result['elapsed_seconds']);
        $this->assertSame(60.0, $result['per_minute']);
    }

    public function test_it_resets_baseline_after_counter_decrease(): void
    {
        $result = (new PageSplitRateService)->calculate(
            previousCount: 1000,
            currentCount: 10,
            previousAt: new DateTimeImmutable('2026-07-18 10:00:00'),
            currentAt: new DateTimeImmutable('2026-07-18 10:05:00'),
            sameCounterEpoch: true,
        );

        $this->assertNull($result['delta']);
        $this->assertNull($result['per_minute']);
    }

    public function test_it_resets_baseline_after_sql_server_restart(): void
    {
        $result = (new PageSplitRateService)->calculate(
            previousCount: 100,
            currentCount: 200,
            previousAt: new DateTimeImmutable('2026-07-18 10:00:00'),
            currentAt: new DateTimeImmutable('2026-07-18 10:05:00'),
            sameCounterEpoch: false,
        );

        $this->assertNull($result['delta']);
    }
}
