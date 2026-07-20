<?php

namespace App\Domain\Analytics\Enums;

enum FindingSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Información',
            self::Warning => 'Advertencia',
            self::Critical => 'Crítica',
        };
    }

    public function toAlertSeverity(): \App\Enums\AlertSeverity
    {
        return match ($this) {
            self::Info => \App\Enums\AlertSeverity::Info,
            self::Warning => \App\Enums\AlertSeverity::Warning,
            self::Critical => \App\Enums\AlertSeverity::Critical,
        };
    }
}