<?php

namespace App\Enums;

enum AuditActorType: string
{
    case System = 'system';
    case WhatsappContact = 'whatsapp_contact';
    case AdminUser = 'admin_user';
}
