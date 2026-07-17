<?php

namespace App\Enums;

enum ContactRole: string
{
    case Dba = 'dba';
    case Approver = 'approver';
    case Viewer = 'viewer';

    /**
     * Solo estos roles pueden aprobar acciones de mantenimiento vía WhatsApp.
     */
    public function canApproveActions(): bool
    {
        return match ($this) {
            self::Dba, self::Approver => true,
            self::Viewer => false,
        };
    }
}
