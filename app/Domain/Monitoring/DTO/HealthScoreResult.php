<?php

namespace App\Domain\Monitoring\DTO;

final readonly class HealthScoreResult
{
    public function __construct(
        public ?int $score,
        public string $version,
        public array $details,
    ) {}
}
