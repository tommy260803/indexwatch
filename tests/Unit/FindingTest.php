<?php

namespace Tests\Unit;

use App\Domain\Analytics\DTO\Finding;
use App\Domain\Analytics\Enums\ConfidenceLevel;
use App\Domain\Analytics\Enums\FindingSeverity;
use App\Domain\Analytics\Enums\FindingType;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use PHPUnit\Framework\TestCase;

class FindingTest extends TestCase
{
    public function test_it_converts_analytics_enums_to_alert_enums(): void
    {
        $finding = new Finding(
            type: FindingType::UnusedIndex,
            fingerprint: 'unused-index-fingerprint',
            severity: FindingSeverity::Warning,
            confidence: ConfidenceLevel::High,
            title: 'Unused index',
            description: 'No reads observed.',
            evidence: [],
            recommendedAction: 'REVIEW',
        );

        $data = $finding->toAlertData();

        $this->assertSame('unused-index-fingerprint', $data['fingerprint']);
        $this->assertSame(AlertType::Inactive, $data['alert_type']);
        $this->assertSame(AlertSeverity::Warning, $data['severity']);
    }
}
