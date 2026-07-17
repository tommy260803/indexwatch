<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Expired = 'expired';
}