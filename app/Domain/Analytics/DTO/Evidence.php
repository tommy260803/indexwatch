<?php

namespace App\Domain\Analytics\DTO;

use App\Domain\Analytics\Enums\ConfidenceLevel;
use App\Domain\Analytics\Enums\FindingSeverity;
use App\Domain\Analytics\Enums\FindingType;
use DateTimeInterface;

final readonly class Evidence
{
    public function __construct(
        public string $key,
        public string $label,
        public mixed $value,
        public ?string $unit = null,
        public ?string $description = null,
    ) {}

    public static function metric(string $key, string $label, mixed $value, ?string $unit = null, ?string $description = null): self
    {
        return new self($key, $label, $value, $unit, $description);
    }

    public static function text(string $key, string $label, string $value, ?string $description = null): self
    {
        return new self($key, $label, $value, null, $description);
    }
}