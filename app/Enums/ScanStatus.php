<?php

namespace App\Enums;

enum ScanStatus: string
{
    case Success = 'success';
    case Error = 'error';
    case Running = 'running';
    case Degraded = 'degraded';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Exitoso',
            self::Error => 'Error',
            self::Running => 'En progreso',
            self::Degraded => 'Parcial',
        };
    }
}
