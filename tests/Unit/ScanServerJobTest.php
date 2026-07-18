<?php

namespace Tests\Unit;

use App\Jobs\ScanServerJob;
use PHPUnit\Framework\TestCase;

class ScanServerJobTest extends TestCase
{
    public function test_correlation_id_survives_queue_serialization_for_retries(): void
    {
        $job = new ScanServerJob(42, '6b80d9ee-f706-4fb4-8a3d-8511a04d949a');

        /** @var ScanServerJob $restored */
        $restored = unserialize(serialize($job));

        $this->assertSame('42', $restored->uniqueId());
        $this->assertSame($job->correlationId, $restored->correlationId);
        $this->assertSame('scans', $restored->queue);
    }
}
