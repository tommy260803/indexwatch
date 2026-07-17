<?php

namespace App\Enums;

enum AuditSource: string
{
    case Whatsapp = 'whatsapp';
    case Dashboard = 'dashboard';
    case Job = 'job';
    case System = 'system';
}