<?php

namespace App\Enums;

enum AuditActorType: string
{
    case System = 'system';
    case WhatsApp = 'whatsapp';
    case User = 'user';
    case Api = 'api';

    public static function fromValue(string $value): self
    {
        return match ($value) {
            'system' => self::System,
            'whatsapp', 'whatsapp_contact' => self::WhatsApp,
            'user', 'admin_user' => self::User,
            'api' => self::Api,
            default => self::System,
        };
    }
}