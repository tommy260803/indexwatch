<?php

namespace App\Domain\Analytics\Enums;

enum ConfidenceLevel: int
{
    case Low = 30;
    case Medium = 60;
    case High = 85;
    case VeryHigh = 95;

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= self::VeryHigh->value => self::VeryHigh,
            $score >= self::High->value => self::High,
            $score >= self::Medium->value => self::Medium,
            default => self::Low,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Baja',
            self::Medium => 'Media',
            self::High => 'Alta',
            self::VeryHigh => 'Muy alta',
        };
    }
}