<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}