<?php

namespace App\Enums;

enum ServerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
            self::Maintenance => 'En mantenimiento',
        };
    }
}
