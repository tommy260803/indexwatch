<?php

namespace App\Services\Monitoring;

use DateTimeInterface;

class PageSplitRateService
{
    /** @return array{delta: ?int, elapsed_seconds: ?int, per_minute: ?float} */
    public function calculate(
        ?int $previousCount,
        int $currentCount,
        ?DateTimeInterface $previousAt,
        DateTimeInterface $currentAt,
        bool $sameCounterEpoch,
    ): array {
        if (! $sameCounterEpoch
            || $previousCount === null
            || $previousAt === null
            || $currentCount < $previousCount) {
            return ['delta' => null, 'elapsed_seconds' => null, 'per_minute' => null];
        }

        $elapsedSeconds = $currentAt->getTimestamp() - $previousAt->getTimestamp();

        if ($elapsedSeconds <= 0) {
            return ['delta' => null, 'elapsed_seconds' => null, 'per_minute' => null];
        }

        $delta = $currentCount - $previousCount;

        return [
            'delta' => $delta,
            'elapsed_seconds' => $elapsedSeconds,
            'per_minute' => (float) ($delta / ($elapsedSeconds / 60)),
        ];
    }
}
