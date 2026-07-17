<?php

namespace App\Enums;

enum AlertStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case AwaitingResponse = 'awaiting_response';
    case Approved = 'approved';           // aprobado por WhatsApp, pendiente de programar
    case Scheduled = 'scheduled';          // programado para una ventana de mantenimiento
    case Running = 'running';              // el Job está ejecutando la acción
    case Succeeded = 'succeeded';          // acción ejecutada y verificada con éxito
    case Failed = 'failed';
    case Expired = 'expired';
    case Dismissed = 'dismissed';

    public static function openStatuses(): array
    {
        return [
            self::Pending, self::Sent, self::AwaitingResponse,
            self::Approved, self::Scheduled, self::Running,
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::openStatuses(), true);
    }
}