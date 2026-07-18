<?php

namespace Tests\Unit;

use App\Services\Monitoring\HealthScoreService;
use Tests\TestCase;

class HealthScoreServiceTest extends TestCase
{
    public function test_score_is_reproducible_and_never_negative(): void
    {
        config()->set('indexwatch.health_score', [
            'version' => 'test-v1',
            'critical_index_penalty' => 10,
            'critical_index_cap' => 50,
            'stale_statistics_penalty' => 5,
            'stale_statistics_cap' => 25,
            'sustained_page_splits_penalty' => 5,
            'sustained_page_splits_cap' => 10,
        ]);

        $result = (new HealthScoreService)->calculate(20, 20, 20);

        $this->assertSame(15, $result->score);
        $this->assertSame('test-v1', $result->version);
        $this->assertSame(85, $result->details['total_deduction']);
    }

    public function test_score_explains_each_deduction(): void
    {
        $result = (new HealthScoreService)->calculate(2, 1, 1);

        $this->assertSame(70, $result->score);
        $this->assertSame(20, $result->details['critical_indexes']['deduction']);
        $this->assertSame(5, $result->details['stale_statistics']['deduction']);
        $this->assertSame(5, $result->details['sustained_page_splits']['deduction']);
        $this->assertTrue($result->details['complete']);
    }

    public function test_incomplete_score_is_not_published_as_healthy(): void
    {
        $result = (new HealthScoreService)->incomplete(['fragmentation']);

        $this->assertNull($result->score);
        $this->assertFalse($result->details['complete']);
        $this->assertSame(['fragmentation'], $result->details['unavailable_components']);
    }
}
